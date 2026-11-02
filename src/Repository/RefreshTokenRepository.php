<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
final class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, RefreshToken::class);
    }

    #[\Override]
    public function findOneByToken(string $token): ?RefreshToken
    {
        $refreshToken = $this->createQueryBuilder('r')
            ->where('r.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $refreshToken instanceof RefreshToken ? $refreshToken : null;
    }
}
