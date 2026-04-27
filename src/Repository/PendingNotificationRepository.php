<?php

/*
 * HiPay payment integration for Sylius
 *
 * (c) Hipay
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace HiPay\SyliusHiPayPlugin\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityRepository;
use HiPay\SyliusHiPayPlugin\Entity\PendingNotification;
use HiPay\SyliusHiPayPlugin\Webhook\PendingNotificationState;
use Throwable;

/**
 * @extends EntityRepository<PendingNotification>
 */
class PendingNotificationRepository extends EntityRepository
{
    /**
     * Atomically claims up to $size rows eligible for processing and marks them
     * as PROCESSING.
     *
     * Eligible rows are:
     *   - PENDING rows whose buffer window has elapsed (available_at <= now); OR
     *   - PROCESSING rows that a previous worker left behind (claimed_at older
     *     than $stalledClaimTimeout): a worker crashed or was killed mid-flight.
     *
     * Ordering is strict (priority ASC, id ASC) so that — assuming a single
     * worker — notifications for the same transaction are applied to the state
     * machine in deterministic priority order. `FOR UPDATE SKIP LOCKED`
     * also makes the method safe to run from N workers in parallel: each worker
     * gets a disjoint batch, at the cost of relaxing the strict order across
     * workers (the per-order `OrderAdvisoryLock` still protects state-machine
     * transitions).
     *
     * Requires MySQL 8.0+ (SKIP LOCKED).
     *
     * @return array<PendingNotification>
     */
    public function claimBatch(int $size, int $stalledClaimTimeoutSeconds = 600): array
    {
        if ($size <= 0) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $now = new DateTimeImmutable();
        $staleCutoff = $now->modify(sprintf('-%d seconds', $stalledClaimTimeoutSeconds));

        $connection->beginTransaction();

        try {
            // LIMIT is interpolated because DBAL/PDO parameter binding for LIMIT
            // is fragile across drivers; the value comes from trusted config.
            $sql = sprintf(
                'SELECT id FROM hipay_pending_notification WHERE ('
                . '(state = ? AND available_at <= ?)'
                . ' OR (state = ? AND claimed_at <= ?)'
                . ') ORDER BY priority ASC, id ASC LIMIT %d FOR UPDATE SKIP LOCKED',
                max(1, $size),
            );

            /** @var list<int|string> $ids */
            $ids = $connection->fetchFirstColumn($sql, [
                PendingNotificationState::PENDING->value,
                $now->format('Y-m-d H:i:s'),
                PendingNotificationState::PROCESSING->value,
                $staleCutoff->format('Y-m-d H:i:s'),
            ]);

            if ([] === $ids) {
                $connection->commit();

                return [];
            }

            $connection->executeStatement(
                'UPDATE hipay_pending_notification SET state = ?, claimed_at = ? WHERE id IN (?)',
                [
                    PendingNotificationState::PROCESSING->value,
                    $now->format('Y-m-d H:i:s'),
                    $ids,
                ],
                [2 => ArrayParameterType::INTEGER],
            );

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        // Entities are loaded outside the claim transaction: the rows are now
        // pinned to PROCESSING in the DB, so no other worker will grab them.
        $intIds = array_map('intval', $ids);

        /** @var array<PendingNotification> $entities */
        $entities = $this->createQueryBuilder('pn')
            ->andWhere('pn.id IN (:ids)')
            ->setParameter('ids', $intIds)
            ->orderBy('pn.priority', 'ASC')
            ->addOrderBy('pn.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $entities;
    }

    public function findOneByEventId(string $eventId): ?PendingNotification
    {
        /** @var ?PendingNotification $entity */
        $entity = $this->findOneBy(['eventId' => $eventId]);

        return $entity;
    }
}
