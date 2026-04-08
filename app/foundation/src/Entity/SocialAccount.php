<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('social_accounts')]
#[Index('uq_provider_provider_id', ['provider', 'provider_id'], unique: true)]
class SocialAccount extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'user_id', references: 'users.id', onDelete: 'cascade')]
    public int $userId;

    #[Column(length: 20)]
    public string $provider;

    #[Column(name: 'provider_id', length: 255)]
    public string $providerId;

    #[Column(name: 'provider_email', length: 255)]
    public ?string $providerEmail = null;

    #[Column(name: 'access_token', type: 'text')]
    public ?string $accessToken = null;

    #[Column(name: 'refresh_token', type: 'text')]
    public ?string $refreshToken = null;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public ?DateTimeImmutable $createdAt = null;
}
