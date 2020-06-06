<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaOwner;
use App\Domain\Entity\MediaSource;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\Entity\TrickGroup;
use App\Domain\Entity\TrickMedia;
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
     */
    public function __construct(RegistryInterface $registry, ResultSetMappingBuilder $resultSetMapping)
    {
        parent::__construct($registry, Trick::class);
        // ResultSetMappingBuilder extends ResultSetMapping.
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
        // TODO: complete query later with users messages or use Message (Comment) Repository to limit result!
        $queryBuilder = $this->createQueryBuilder('t');
        // No need to join media types and trick group due to no particular filter on data
        // trick group and media type data are added automatically thanks to lazy loading!
        // Specifying joins reduces query numbers!
        $result = $queryBuilder
            // IMPORTANT! This feeds all objects properties correctly!
            ->select(['t', 'tg','mo', 'm', 'mt', 'ms', 'i', 'v'])
            ->leftJoin('t.trickGroup', 'tg', 'WITH', 't.trickGroup = tg.uuid')
            ->leftJoin('t.mediaOwner', 'mo', 'WITH', 'mo.trick = t.uuid')
            ->leftJoin('mo.medias', 'm', 'WITH', 'm.mediaOwner = mo.uuid')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            ->leftJoin('m.mediaSource', 'ms', 'WITH', 'm.mediaSource = ms.uuid')
            ->leftJoin('ms.image', 'i', 'WITH', 'ms.image = i.uuid')
            ->leftJoin('ms.video', 'v', 'WITH', 'ms.video = v.uuid')
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
        // TODO: complete query later with users messages or use Message (Comment) Repository to limit result!
        $queryBuilder = $this->createQueryBuilder('t');
        // No need to join media types and trick group due to no particular filter on data
        // trick group and media type data are added automatically thanks to lazy loading!
        // Specifying joins reduces query numbers!
        $result = $queryBuilder
            // IMPORTANT! This feeds all objects properties correctly!
            ->select(['t', 'tg','mo', 'm', 'mt', 'ms', 'i', 'v']) // same as ['t', 'tg', 'tm', 'm', 'mt', 'i', 'v']
            // CAUTION! 'HIDDEN' is a DQL keyword and
            // uses 'INVISIBLE' (or limited query result "tips") equivalence for MariaDB/MySQL up to date server version,
            // not to keep this column in final result.
            ->addSelect('FIELD(mt.sourceType, ?8, ?9) AS HIDDEN ordered_media_source_type')
            ->leftJoin('t.trickGroup', 'tg', 'WITH', 't.trickGroup = tg.uuid')
            ->leftJoin('t.mediaOwner', 'mo', 'WITH', 'mo.trick = t.uuid')
            ->leftJoin('mo.medias', 'm', 'WITH', 'm.mediaOwner = mo.uuid')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            ->leftJoin('m.mediaSource', 'ms', 'WITH', 'm.mediaSource = ms.uuid')
            ->leftJoin('ms.image', 'i', 'WITH', 'ms.image = i.uuid')
            ->leftJoin('ms.video', 'v', 'WITH', 'ms.video = v.uuid')
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
            ->orderBy('ordered_media_source_type')
            // Each group of medias sorted earlier by types are sorted by show list rank data.
            ->addOrderBy('m.showListRank', 'ASC')
            ->setParameter(1, $uuid->getBytes())
            ->setParameter(2, MediaType::TYPE_CHOICES['trickThumbnail'])
            ->setParameter(3, MediaType::TYPE_CHOICES['trickNormal'])
            ->setParameter(4, MediaType::TYPE_CHOICES['trickBig'])
            ->setParameter(5, MediaType::TYPE_CHOICES['trickYoutube'])
            ->setParameter(6, MediaType::TYPE_CHOICES['trickVimeo'])
            ->setParameter(7, MediaType::TYPE_CHOICES['trickDailymotion'])
            ->setParameter(8, MediaType::SOURCE_TYPES[0]) // image
            ->setParameter(9, MediaType::SOURCE_TYPES[1]) // video
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
            FROM
            (
                -- Sub query to get trick ordered with descending/ascending order
                SELECT o.*, @curRank := IF (@sortDirection = 'DESC', @curRank - 1, @curRank + 1) AS `rank`
                FROM 
                (
                    -- Sub query to order tricks by date with sort direction 
                    -- and retrieve associated entities with necessary data for trick list
                    SELECT t.uuid, t.name AS t_name, t.slug, t.creation_date, 
                           tg.uuid AS tg_uuid, tg.name AS tg_name,
                           mo.uuid AS mo_uuid,
                           m.uuid AS m_uuid,
                           mt.uuid AS mt_uuid, mt.type,
                           ms.uuid AS ms_uuid,
                           i.uuid AS i_uuid, i.name AS i_name, i.description, i.format
                    FROM
                    (
                        -- Init user variables with parameters using SELECT
                        -- @curRank is initialized with -1 (ASC) or tricks total count + 1 (DESC).
                        SELECT @curRank := :initRank, @sortDirection := :sortDirection
                    ) AS s, 
                    tricks t
                    INNER JOIN trick_groups tg ON t.trick_group_uuid = tg.uuid
                    INNER JOIN media_owners mo ON mo.trick_uuid = t.uuid 
                    INNER JOIN medias m ON m.media_owner_uuid = mo.uuid    
                    INNER JOIN media_types mt ON m.media_type_uuid = mt.uuid
                    INNER JOIN media_sources ms ON m.media_source_uuid = ms.uuid
                    INNER JOIN images i ON ms.image_uuid = i.uuid
                    -- Filter with media type
                    WHERE mt.type = :mediaType
                    -- Filter with image main status 
                    -- IMPORTANT: avoid issue by selecting, for each trick, only 1 one thumb image which the main one 
                    AND m.is_main = 1
                    ORDER BY
                    CASE WHEN @sortDirection = 'DESC' THEN t.creation_date END DESC,
                    CASE WHEN @sortDirection = 'ASC' THEN t.creation_date END
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
            ->addFieldResult('t', 't_name', 'name')
            ->addFieldResult('t', 'slug', 'slug')
            ->addFieldResult('t', 'creation_date', 'creationDate')
            ->addScalarResult('rank', 'rank', 'integer');
        $resultSetMapping->addJoinedEntityResult(TrickGroup::class , 'tg', 't', 'trickGroup')
            ->addFieldResult('tg', 'tg_uuid', 'uuid')
            ->addFieldResult('tg', 'tg_name', 'name');
       $resultSetMapping->addJoinedEntityResult(MediaOwner::class , 'mo', 't', 'mediaOwner')
            ->addFieldResult('mo', 'mo_uuid', 'uuid');
        $resultSetMapping->addJoinedEntityResult(Media::class , 'm', 'mo', 'medias')
            ->addFieldResult('m', 'm_uuid', 'uuid');
        $resultSetMapping->addJoinedEntityResult(MediaType::class , 'mt', 'm', 'mediaType')
            ->addFieldResult('mt', 'mt_uuid', 'uuid')
            ->addFieldResult('mt', 'mt_type', 'type');
        $resultSetMapping->addJoinedEntityResult(MediaSource::class , 'ms', 'm', 'mediaSource')
            ->addFieldResult('ms', 'ms_uuid', 'uuid');
        $resultSetMapping->addJoinedEntityResult(Image::class , 'i', 'ms', 'image')
            ->addFieldResult('i', 'i_uuid', 'uuid')
            ->addFieldResult('i', 'i_name', 'name')
            ->addFieldResult('i', 'description', 'description')
            ->addFieldResult('i', 'format', 'format');
        $query = $this->_em->createNativeQuery($customSQL, $this->resultSetMapping);
        return $query;
    }
}
