<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\Entity\TrickGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class TrickRepository.
 *
 * Manage Trick entity data in database.
 */
class TrickRepository extends ServiceEntityRepository
{
    /**
     * @var ResultSetMappingBuilder;
     */
    private $resultSetMapping;

    /**
     * TrickRepository constructor.
     *
     * @param RegistryInterface       $registry
     * @param ResultSetMappingBuilder $resultSetMapping
     *
     * @return void
     */
    public function __construct(RegistryInterface $registry, ResultSetMappingBuilder $resultSetMapping)
    {
        parent::__construct($registry, Trick::class);
        // ResultSetMappingBuilder extends ResultSetMapping
        $this->resultSetMapping = $resultSetMapping;
    }

    /**
     * Count total number of Trick entities.
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function countAll() : int
    {
        $queryBuilder = $this->createQueryBuilder('t');
        return (int) $queryBuilder
            ->select($queryBuilder->expr()->count('t.uuid'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retrieve rows between $start (included) and $end (excluded) with sort direction.
     *
     * Please note only one query is used to get all need Trick data.
     * For instance, this method can load new tricks to show in trick list on homepage.
     *
     * @param string $order
     * @param int    $init
     * @param int    $start
     * @param int    $end
     *
     * @see Dynamic MySQL column name:
     * https://stackoverflow.com/questions/26795844/mysql-dynamic-column-alias
     * @see MySQL WHERE clause:
     * https://codeburst.io/filtering-select-queries-with-the-where-clause-in-mysql-b35740227e04
     *
     * @return array|null
     */
    public function findByLimitOffsetWithOrder(
        string $order,
        int $init,
        int $start,
        int $end
    ) : ?array {
        // Get custom SQL query string to use it as native query with ResultSetMapping instance
        $customSQL = $this->getTrickListCustomSQL();
        // get a native query thanks to a ResultSetMappingBuilder instance
        $customQuery = $this->getTrickListCustomNativeQuery($customSQL, $this->resultSetMapping);
        // Use parameters to prepare the query and get result(s)
        $result = $customQuery
            ->setParameter('initRank', $init)
            ->setParameter('sortDirection', $order)
            ->setParameter('mediaType', MediaType::TYPE_CHOICES['trickThumbnail'])
            ->setParameter('startRank', $start)
            ->setParameter('endRank', $end)
            ->getResult();
        $count = \count($result);
        // No trick(s) is/are found!
        if (0 === $count) {
            return null;
        }
        $tricks = [];
        // Rearrange results array to loop easily in template with rank
        for ($i = 0; $i < $count; $i ++) {
            $tricks[$i] = $result[$i][0];
            $tricks[$i]->assignRank($result[$i]['rank']);
        }
        // Return an array of objects with tricks data
        return $tricks;
    }

    /**
     * Find a Trick entity with query based on its name.
     *
     * Please note only one query is used to get all need Trick data.
     *
     * @param string $name
     *
     * @return Trick|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByName(string $name) : ?Trick
    {
        // TODO: complete query later with users messages or use Message Repository to limit result!
        $queryBuilder = $this->createQueryBuilder('t');
        // No need to join media types and trick group due to no particular filter on data
        // trick group and media type data are added automatically thanks to lazy loading!
        // Specifying joins reduces query numbers!
        $result = $queryBuilder
            // IMPORTANT! This feeds all objects properties correctly!
            ->select(['t', 'tg', 'm', 'i', 'v'])
            ->leftJoin('t.trickGroup', 'tg', 'WITH', 't.trickGroup = tg.uuid')
            ->leftJoin('t.medias', 'm', 'WITH', 'm.trick = t.uuid')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            ->leftJoin('m.image', 'i', 'WITH', 'm.image = i.uuid')
            ->leftJoin('m.video', 'v', 'WITH', 'm.video = v.uuid')
            ->where('t.name = ?1')
            ->orderBy('i.creationDate', 'DESC')
            ->addOrderBy('v.creationDate', 'DESC')
            ->setParameter(1, $name)
            ->getQuery()
            ->getOneOrNullResult();
        return $result;
    }

    /**
     * Find a Trick entity with query based on its uuid.
     *
     * Please note only one query is used to get all need Trick data.
     *
     * @param UuidInterface $uuid
     *
     * @link: please not DQL sub query is not supported in SELECT clause:
     * https://github.com/doctrine/orm/issues/6372
     *
     * @see: binary string to store uuid
     * https://mysqlserverteam.com/storing-uuid-values-in-mysql-tables/
     * @see: convert hex to bin and bin to hex
     * https://stackoverflow.com/questions/28251144/inserting-and-selecting-uuids-as-binary16
     * @see: binary string shortcut
     * https://docs.benramsey.com/ramsey-uuid/3.7.3/Ramsey/Uuid/Uuid.html#method_getBytes
     * @see: use of string codec instead of uuid:
     * https://github.com/ramsey/uuid/blob/29fb62b48611761b4c0c4e8f4a428cad19c2b690/src/Codec/StringCodec.php#L61-L100
     *
     * @return Trick|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByUuid(UuidInterface $uuid) : ?Trick
    {
        // TODO: complete query later with users messages or use Message Repository to limit result!
        $queryBuilder = $this->createQueryBuilder('t');
        // No need to join media types and trick group due to no particular filter on data
        // trick group and media type data are added automatically thanks to lazy loading!
        // Specifying joins reduces query numbers!
        $result = $queryBuilder
            // IMPORTANT! This feeds all objects properties correctly!
            // Column declaration 't, tg, m, i, v' is equivalent to ['t', 'tg', 'm', 'mt', 'i', 'v'] syntax.
            ->select('t, tg, m, i, v')
            // CAUTION! 'HIDDEN' is a DQL keyword and
            // uses 'INVISIBLE' (or limited query result "tips") equivalence for MariaDB/MySQL up to date server version,
            // not to keep this column in final result.
            ->addSelect('FIELD(mt.type, ?2, ?5, ?6, ?7) AS HIDDEN ordered_media_type')
            ->leftJoin('t.trickGroup', 'tg', 'WITH', 't.trickGroup = tg.uuid')
            ->leftJoin('t.medias', 'm', 'WITH', 'm.trick = t.uuid')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            // "m.image" should be replaced by "ms.uuid" with ms as a new "media_sources" table!
            ->leftJoin('m.image', 'i', 'WITH', 'm.image = i.uuid')
            // "m.video" should be replaced by "ms.uuid" with ms as a new "media_sources" table!
            ->leftJoin('m.video', 'v', 'WITH', 'm.video = v.uuid')
            // Get big image format only for main image
            ->where('mt.type = ?4')
            ->andWhere('m.isMain = ' . $queryBuilder->expr()->literal('1'))
            // Get other desired types in retrieved trick medias
            ->orWhere('mt.type = ?2')
            ->orWhere('mt.type = ?3')
            ->orWhere('mt.type = ?5')
            ->orWhere('mt.type = ?6')
            ->orWhere('mt.type = ?7')
            // Expression is equivalent to 't.uuid= ?1' in WHERE clause.
            ->andWhere($queryBuilder->expr()->eq('t.uuid', '?1'))
            // List is ordered by these particular media types. FIELD() function can be directly used here to simplify query!
            ->orderBy('ordered_media_type')
            // Each group of medias sorted earlier by types are sorted by show list rank data.
            ->addOrderBy('m.showListRank', 'ASC')
            ->setParameter(1, $uuid->getBytes())
            ->setParameter(2, MediaType::TYPE_CHOICES['trickThumbnail'])
            ->setParameter(3, MediaType::TYPE_CHOICES['trickNormal'])
            ->setParameter(4, MediaType::TYPE_CHOICES['trickBig'])
            ->setParameter(5, MediaType::TYPE_CHOICES['trickYoutube'])
            ->setParameter(6, MediaType::TYPE_CHOICES['trickVimeo'])
            ->setParameter(7, MediaType::TYPE_CHOICES['trickDailymotion'])
            ->getQuery()
            ->getOneOrNullResult();
        return $result;
    }

    /**
     * Get trick list custom SQL.
     *
     * @return string
     */
    private function getTrickListCustomSQL() : string
    {
        // SQL query
        return $customSQL = "
            SELECT r.*
            -- SELECT r.*, g.name AS trick_group_name, m.*, mt.*
            FROM
            (
                -- Sub query to get trick ordered with descending/ascending order
                SELECT o.*, @curRank := IF (@sortDirection = 'DESC', @curRank - 1, @curRank + 1) AS `rank`
                FROM 
                (
                    -- Sub query to order tricks by date with sort direction 
                    -- and retrieve associated entities with necessary data for trick list
                    SELECT t.uuid, t.name, t.slug, t.creation_date, 
                           tg.uuid AS tg_uuid, tg.name AS tg_name, 
                           m.uuid AS m_uuid, m.image_uuid, m.media_type_uuid, m.trick_uuid,
                           mt.uuid AS mt_uuid, mt.type,
                           i.uuid AS i_uuid, i.name AS i_name, i.description, i.format
                    FROM
                    (
                        -- Init user variables with parameters using SELECT
                        -- @curRank is initialized with -1 (ASC) or tricks total count + 1 (DESC).
                        SELECT @curRank := :initRank, @sortDirection := :sortDirection
                    ) AS s, 
                    tricks t
                    INNER JOIN trick_groups tg ON t.trick_group_uuid = tg.uuid
                    INNER JOIN medias m ON m.trick_uuid = t.uuid
                    INNER JOIN media_types mt ON m.media_type_uuid = mt.uuid
                    INNER JOIN images i ON m.image_uuid = i.uuid
                    -- Filter with media type
                    WHERE mt.type = :mediaType
                    -- Filter with image main status 
                    -- IMPORTANT: avoid issue by selecting, for each trick, only 1 one thumb image which the main one 
                    AND m.is_main = 1
                    ORDER BY
                    CASE WHEN @sortDirection = 'DESC' THEN t.creation_date END DESC,
                    CASE WHEN @sortDirection = 'ASC' THEN t.creation_date END ASC
                ) AS o
            ) AS r
            -- Filter with parameters to define expected rank interval instead of using LIMIT and OFFSET definition
            WHERE r.rank >= :startRank AND r.rank < :endRank
        ";
    }

    /**
     * Get a custom Native query instance.
     *
     * @param string                  $customSQL
     * @param ResultSetMappingBuilder $resultSetMapping
     *
     * @return NativeQuery
     */
    private function getTrickListCustomNativeQuery(string $customSQL, ResultSetMappingBuilder $resultSetMapping) : NativeQuery
    {
        $resultSetMapping->addEntityResult(Trick::class, 't')
            ->addFieldResult('t', 'uuid', 'uuid')
            ->addFieldResult('t', 'name', 'name')
            ->addFieldResult('t', 'slug', 'slug')
            ->addFieldResult('t', 'creation_date', 'creationDate')
            ->addScalarResult('rank', 'rank', 'integer');
        $resultSetMapping->addJoinedEntityResult(TrickGroup::class , 'tg', 't', 'trickGroup')
            ->addFieldResult('tg', 'tg_uuid', 'uuid')
            ->addFieldResult('tg', 'tg_name', 'name');
        $resultSetMapping->addJoinedEntityResult(Media::class , 'm', 't', 'medias')
            ->addFieldResult('m', 'm_uuid', 'uuid');
        $resultSetMapping->addJoinedEntityResult(MediaType::class , 'mt', 'm', 'mediaType')
            ->addFieldResult('mt', 'mt_uuid', 'uuid')
            ->addFieldResult('mt', 'type', 'type');
        $resultSetMapping->addJoinedEntityResult(Image::class , 'i', 'm', 'image')
            ->addFieldResult('i', 'i_uuid', 'uuid')
            ->addFieldResult('i', 'i_name', 'name')
            ->addFieldResult('i', 'description', 'description')
            ->addFieldResult('i', 'format', 'format');
        $query = $this->_em->createNativeQuery($customSQL, $this->resultSetMapping);
        return $query;
    }
}
