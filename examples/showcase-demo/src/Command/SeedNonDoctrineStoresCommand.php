<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ProductRepository;
use League\Flysystem\FilesystemOperator;
use Meilisearch\Endpoints\Indexes;
use Predis\ClientInterface as PredisClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seed the 3 non-Doctrine backing stores so the Polysource adapter
 * resources have data to render on first page load:
 *
 *  - Redis     → 30 cache entries under shopco:cache:*
 *  - MinIO/S3  → 10 invoice PDFs + 5 product photos
 *  - Meilisearch → push every Foundry-seeded product into shopco-products
 *
 * Idempotent: safe to re-run. Drops existing entries before re-seeding
 * so the row counts stay deterministic for screenshots.
 */
#[AsCommand(
    name: 'app:seed-stores',
    description: 'Seed Redis cache + MinIO bucket + Meilisearch index with realistic ShopCo data.',
)]
final class SeedNonDoctrineStoresCommand extends Command
{
    public function __construct(
        private readonly PredisClient $redis,
        private readonly FilesystemOperator $filesystem,
        #[Autowire(service: 'app.meilisearch.products_index')]
        private readonly Indexes $meiliIndex,
        private readonly ProductRepository $products,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('1/3 — Redis cache');
        $this->seedRedis($io);

        $io->section('2/3 — MinIO bucket');
        $this->seedMinio($io);

        $io->section('3/3 — Meilisearch index');
        $this->seedMeilisearch($io);

        $io->success('All non-Doctrine stores seeded.');

        return Command::SUCCESS;
    }

    private function seedRedis(SymfonyStyle $io): void
    {
        // Drop pre-existing showcase keys.
        $existing = $this->redis->keys('shopco:cache:*');
        foreach ($existing as $key) {
            $this->redis->del($key);
        }

        $count = 0;

        // Product hot cache — 20 entries.
        for ($i = 1; $i <= 20; ++$i) {
            $key = sprintf('shopco:cache:product:hot:%04d', $i);
            $this->redis->hmset($key, [
                'id' => sprintf('prd-%04d', $i),
                'name' => sprintf('Hot product #%d', $i),
                'priceCents' => (string) random_int(500, 18000),
                'stock' => (string) random_int(0, 250),
                'cachedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ]);
            $this->redis->expire($key, 3600);
            ++$count;
        }

        // Cart sessions — 10 entries.
        for ($i = 1; $i <= 10; ++$i) {
            $key = sprintf('shopco:cache:cart:%s', bin2hex(random_bytes(8)));
            $this->redis->hmset($key, [
                'id' => bin2hex(random_bytes(8)),
                'items' => (string) random_int(1, 5),
                'totalCents' => (string) random_int(2500, 35000),
                'updatedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ]);
            $this->redis->expire($key, 1800);
            ++$count;
        }

        $io->writeln(sprintf('  → %d keys written under shopco:cache:*', $count));
    }

    private function seedMinio(SymfonyStyle $io): void
    {
        // Drop pre-existing showcase files (best-effort).
        try {
            foreach ($this->filesystem->listContents('', true) as $entry) {
                if ($entry->isFile()) {
                    $this->filesystem->delete($entry->path());
                }
            }
        } catch (\Throwable) {
            // Bucket may be empty / missing — recreated on first write.
        }

        $count = 0;

        // 10 invoice PDFs — flat naming so Polysource's detail route
        // (which forbids "/" in identifiers) can render their links.
        for ($i = 1; $i <= 10; ++$i) {
            $path = sprintf('INV-2026-%02d-%04d.pdf', $i, 1000 + $i);
            $this->filesystem->write(
                $path,
                $this->fakePdfBytes(sprintf('ShopCo Invoice INV-2026-%04d', 1000 + $i)),
            );
            ++$count;
        }

        // 5 product photos — png placeholders.
        for ($i = 1; $i <= 5; ++$i) {
            $path = sprintf('product-photo-%04d.png', $i);
            $this->filesystem->write(
                $path,
                $this->fakePngBytes(),
            );
            ++$count;
        }

        $io->writeln(sprintf('  → %d files written to bucket', $count));
    }

    private function seedMeilisearch(SymfonyStyle $io): void
    {
        // Pull active products from Postgres and push to Meilisearch.
        $documents = [];
        foreach ($this->products->findAll() as $product) {
            $documents[] = [
                'id' => $product->getId()->toRfc4122(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'description' => $product->getDescription(),
                'priceCents' => $product->getPriceCents(),
                'currency' => $product->getCurrency(),
                'stock' => $product->getStock(),
                'status' => $product->getStatus(),
                'category' => $product->getCategory(),
                'createdAt' => $product->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        if ($documents !== []) {
            $task = $this->meiliIndex->addDocuments($documents);
            $io->writeln(sprintf(
                '  → %d documents pushed (task #%s, status: %s)',
                \count($documents),
                $task['taskUid'] ?? $task['uid'] ?? 'n/a',
                $task['status'] ?? 'enqueued',
            ));
        } else {
            $io->writeln('  → no products to push (load fixtures first).');
        }
    }

    private function fakePdfBytes(string $title): string
    {
        // 300-byte valid minimal PDF stub — enough for Flysystem to
        // report a real mime/size, no need for full PDF rendering.
        $body = "%PDF-1.4\n%\xC3\xA0\xC3\xA1\xC3\xA2\xC3\xA3\n";
        $body .= '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj ';
        $body .= '2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj ';
        $body .= sprintf('3 0 obj<</Title(%s)>>endobj ', $title);
        $body .= "trailer<</Root 1 0 R>>\n%%EOF";

        return $body;
    }

    private function fakePngBytes(): string
    {
        // 1×1 transparent PNG — base64 decodes to 67 bytes.
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNgAAIAAAUAAen63NgAAAAASUVORK5CYII=',
            true,
        );
    }
}
