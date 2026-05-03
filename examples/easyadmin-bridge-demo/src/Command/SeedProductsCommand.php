<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Command;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Demo\EasyAdminBridge\Entity\Category;
use Polysource\Demo\EasyAdminBridge\Entity\Product;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'polysource:demo:seed-products',
    description: 'Seed sample categories and products to exercise every filter type.',
)]
final class SeedProductsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $categoryNames = ['Electronics', 'Books', 'Clothing', 'Home & Garden', 'Sports'];
        $categories = [];
        foreach ($categoryNames as $name) {
            $cat = (new Category())->setName($name);
            $this->em->persist($cat);
            $categories[] = $cat;
        }

        $statuses = [Product::STATUS_DRAFT, Product::STATUS_PUBLISHED, Product::STATUS_ARCHIVED];
        $tagPool = ['featured', 'sale', 'new', 'bestseller', 'limited', 'eco', 'imported'];

        for ($i = 1; $i <= 30; ++$i) {
            $price = number_format(random_int(500, 50000) / 100, 2, '.', '');
            $stock = random_int(0, 500);
            $isActive = (bool) random_int(0, 1);
            $status = $statuses[$i % 3];
            $tagsCount = random_int(0, 3);
            $tags = $tagsCount > 0 ? array_rand(array_flip($tagPool), $tagsCount) : [];
            if (\is_string($tags)) {
                $tags = [$tags];
            }
            $createdAt = (new \DateTimeImmutable())->modify(sprintf('-%d days', random_int(0, 90)));
            $archivedAt = Product::STATUS_ARCHIVED === $status
                ? $createdAt->modify('+30 days')
                : null;

            $product = (new Product())
                ->setName(sprintf('Product #%02d', $i))
                ->setDescription("Sample description for product $i — long enough to be searchable")
                ->setPrice($price)
                ->setStock($stock)
                ->setIsActive($isActive)
                ->setStatus($status)
                ->setTags(array_values($tags))
                ->setCreatedAt($createdAt)
                ->setArchivedAt($archivedAt)
                ->setCategory($categories[($i - 1) % \count($categories)])
            ;
            $this->em->persist($product);
        }

        $this->em->flush();

        $io->success('Seeded 5 categories and 30 products.');
        $io->note('Open http://localhost:8081/admin/ — login: admin / admin');

        return Command::SUCCESS;
    }
}
