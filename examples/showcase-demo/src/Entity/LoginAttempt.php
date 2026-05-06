<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Append-only security audit table.
 *
 * Intentionally NOT exposed via EasyAdmin — there's no create/edit
 * use case (the firewall writes rows, ops only reads). Polysource
 * exposes it via {@see App\Polysource\Resource\LoginAttemptResource}
 * with whitelisted filters for ops-grade triage.
 */
#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
#[ORM\Table(name: 'shopco_login_attempt')]
#[ORM\Index(name: 'idx_shopco_login_attempt_status', columns: ['status'])]
#[ORM\Index(name: 'idx_shopco_login_attempt_email', columns: ['email'])]
#[ORM\Index(name: 'idx_shopco_login_attempt_ip', columns: ['ip'])]
#[ORM\Index(name: 'idx_shopco_login_attempt_occurred_at', columns: ['occurred_at'])]
class LoginAttempt
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_BAD_CREDENTIALS = 'bad_credentials';
    public const STATUS_USER_NOT_FOUND = 'user_not_found';
    public const STATUS_RATE_LIMITED = 'rate_limited';
    public const STATUS_BLOCKED = 'blocked';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_SUCCESS,
            self::STATUS_BAD_CREDENTIALS,
            self::STATUS_USER_NOT_FOUND,
            self::STATUS_RATE_LIMITED,
            self::STATUS_BLOCKED,
        ];
    }

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 45)]
    private string $ip = '';

    #[ORM\Column(length: 400, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_BAD_CREDENTIALS;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }
}
