<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

/**
 * Class CommentRepository.
 *
 * Manage Comment entity data in database.
 *
 * For personal information:
 * @see https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/pagination.html#pagination
 * @see https://stackoverflow.com/questions/2480179/anonymous-recursive-php-functions
 * @see https://stackoverflow.com/questions/58473139/obtain-rank-using-doctrine-2-orm-and-onetoone-in-repository
 * @see https://stackoverflow.com/questions/3946709/1222-the-used-select-statements-have-a-different-number-of-columns
 * @see https://stackoverflow.com/questions/6637506/doing-a-where-in-subquery-in-doctrine-2
 * @see https://stackoverflow.com/questions/9831985/selecting-from-subquery-in-dql
 * @see https://stackoverrun.com/fr/q/6203408
 */
class CommentRepository extends ServiceEntityRepository
{
    /**
     * @var ResultSetMappingBuilder;
     */
    private $resultSetMapping;

    /**
     * CommentRepository constructor.
     *
     * @param ManagerRegistry         $registry
     * @param ResultSetMappingBuilder $resultSetMapping
     */
    public function __construct(ManagerRegistry $registry, ResultSetMappingBuilder $resultSetMapping)
    {
        parent::__construct($registry, Comment::class);
        $this->resultSetMapping = $resultSetMapping;
    }

    /**
     * Count total number of Comment entities for a particular trick.
     *
     * @param UuidInterface $trickUuid
     * @param bool          $isAtFirstLevel must retrieve only first level comments (parent comment is null)
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAllByTrick(UuidInterface $trickUuid, bool $isAtFirstLevel = false): int
    {
        $queryBuilder = $this->createQueryBuilder('c');
        $queryBuilder->select($queryBuilder->expr()->count('c.uuid'));
        // Get only comments count at first level with this filter
        !$isAtFirstLevel ?: $queryBuilder->where($queryBuilder->expr()->isNull('c.parentComment'));
        // Count all trick comments
        return (int) $queryBuilder
            ->andWhere('c.trick = ?1')
            ->setParameter(1, $trickUuid->getBytes())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find comments expected data with query based on their associated trick uuid,
     * depending on creation date sort order.
     *
     * @param UuidInterface $trickUuid
     * @param string        $order       a sort order to use with comment creation date
     * @param bool          $hasUuidOnly retrieve comments uuid data only
     *
     * @return array
     */
    public function findAllByTrick(
        UuidInterface $trickUuid,
        string $order = 'ASC',
        bool $hasUuidOnly = false
     ): array {
        // Get comments entities or comments uuid data only
        $queryBuilder = $this->createQueryBuilder('c');
        $query = $queryBuilder
            ->select('c.uuid')
            ->andWhere('c.trick = ?1')
            ->orderBy('c.creationDate', $order)
            ->setParameter(1, $trickUuid->getBytes())
            ->getQuery();
        return $results = $hasUuidOnly ? $query->getScalarResult() : $query->getResult();
    }

    /**
     * Find comments (parents and their children) expected data with query based on their associated trick uuid,
     * depending on offset, limit, creation date sort order and first level condition.
     *
     * @param UuidInterface $trickUuid
     * @param int           $offset
     * @param int           $limit
     * @param string        $order          a sort order to use with comment creation date
     * @param bool          $isAtFirstLevel must retrieve only first level comments (parent comment is null)
     *
     * @return \IteratorAggregate|Paginator
     *
     * @throws \Exception
     */
    public function findAllByTrickWithOffsetLimit(
        UuidInterface $trickUuid,
        int $offset,
        int $limit,
        string $order = 'ASC',
        bool $isAtFirstLevel = false
    ): \IteratorAggregate {
        // Filter order parameter by whitelisting
        $order = \in_array($order, ['ASC', 'DESC']) ? $order : 'ASC';
        // Get comments (parents and children) depending on offset, limit and sort order
        $queryBuilder = $this->createQueryBuilder('c');
        !$isAtFirstLevel ?: $queryBuilder->where($queryBuilder->expr()->isNull('c.parentComment'));
        // Get query results
        $commentEntries = new Paginator(
            $queryBuilder
                ->addSelect('c2')
                ->leftJoin('c.children', 'c2', 'WITH', 'c2.parentComment = c.uuid')
                ->andWhere('c.trick = ?1')
                ->orderBy('c.creationDate', $order)
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->setParameter(1, $trickUuid->getBytes())
                ->getQuery(),
                // This is the default value, but it is specified explicitly
                // since it is fundamental to have correct results!
                true
          );
        // Return comments selected list
        return $commentEntries;
    }

    /**
     * Retrieve and assign comments ranks thanks to a simple query in order to compare uuid,
     * depending on comment list results.
     *
     * @param array              $trickCommentsUuidData
     * @param \IteratorAggregate $commentEntries
     * @param string             $order
     *
     * @return \IteratorAggregate|Paginator
     */
    public function findCommentsRanks(
        array $trickCommentsUuidData,
        \IteratorAggregate $commentEntries,
        string $order = 'ASC'
    ): \IteratorAggregate {
        // Get uuid data count obtained with a second simple query to use it for comparison
        // This count corresponds to trick comments total count!
        $commentCount = \count($trickCommentsUuidData);
        // Prepare Closure not to repeat rank assigning process
        $function = function (Comment $comment) use (&$function, $trickCommentsUuidData, $commentCount, $order) {
            for ($i = 0; $i < $commentCount; $i++) {
                if ($trickCommentsUuidData[$i]['uuid'] === $comment->getUuid()->getBytes()) {
                    $comment->assignRank('DESC' === $order ? ($commentCount - 1 - $i) : $i);
                    break;
                }
            }
            /** @var Comment $comment */
            $childCommentEntries = $comment->getChildren();
            if ($childCommentEntries->count() !== 0) {
                foreach ($childCommentEntries as $childComment) {
                    // Call closure recursively
                    $function($childComment);
                }
            }
        };
        // Assign rank to limited results (parent and children comments) thanks to Closure, comparing uuid one by one
        foreach ($commentEntries as $comment) {
            $function($comment);
        }
        // Return comments with ranks data
        return $commentEntries;
    }
}
