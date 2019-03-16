<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\Entity\TrickGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     */
    public function countAll() : int
    {
        $queryBuilder = $this->createQueryBuilder('t' );
        return (int) $queryBuilder
            ->select($queryBuilder->expr()->count('t.uuid' ))
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Retrieve rows between $start (included) and $end (excluded) with sort direction.
     *
     * For instance, this method can load new tricks to show in trick list on homepage.
     *
     * @param string $order
     * @param int    $init
     * @param int    $start
     * @param int    $end
     *
     * @return array
     */
    public function findByLimitOffsetWithOrder(
        string $order,
        int $init,
        int $start,
        int $end
    ) : array {
        // SQL query to use as native query
        $customSQL = "
            SELECT r.*
            -- SELECT r.*, g.name AS trick_group_name, m.*, mt.*
            FROM
            (
                -- Sub query to get trick ordered with descending/ascending order
                SELECT o.*, @curRank := IF (@sortDirection = 'DESC', @curRank - 1, @curRank + 1) AS rank 
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
                        SELECT @curRank := ?, @sortDirection := ?
                    ) s, 
                    tricks t
                    INNER JOIN trick_groups tg ON t.trick_group_uuid = tg.uuid
                    INNER JOIN medias m ON m.trick_uuid = t.uuid
                    INNER JOIN media_types mt ON m.media_type_uuid = mt.uuid
                    INNER JOIN images i ON m.image_uuid = i.uuid
                    WHERE mt.type = ?
                    ORDER BY
                    CASE WHEN @sortDirection = 'DESC' THEN t.creation_date END DESC,
                    CASE WHEN @sortDirection = 'ASC' THEN t.creation_date END ASC
                ) o
            ) r
            -- Filter with parameters to define expected interval
            WHERE r.rank >= ? AND r.rank < ?
        ";
        $this->resultSetMapping->addEntityResult(Trick::class, 't')
            ->addFieldResult('t', 'uuid', 'uuid')
            ->addFieldResult('t', 'name', 'name')
            ->addFieldResult('t', 'slug', 'slug')
            ->addFieldResult('t', 'creation_date', 'creationDate')
            ->addScalarResult('rank', 'rank','integer');
        $this->resultSetMapping->addJoinedEntityResult(TrickGroup::class , 'tg', 't', 'trickGroup')
            ->addFieldResult('tg', 'tg_uuid', 'uuid')
            ->addFieldResult('tg', 'tg_name', 'name');
        $this->resultSetMapping->addJoinedEntityResult(Media::class , 'm', 't', 'medias')
            ->addFieldResult('m', 'm_uuid', 'uuid');
        $this->resultSetMapping->addJoinedEntityResult(MediaType::class , 'mt', 'm', 'mediaType')
            ->addFieldResult('mt', 'mt_uuid', 'uuid')
            ->addFieldResult('mt', 'type', 'type');
        $this->resultSetMapping->addJoinedEntityResult(Image::class , 'i', 'm', 'image')
            ->addFieldResult('i', 'i_uuid', 'uuid')
            ->addFieldResult('i', 'i_name', 'name')
            ->addFieldResult('i', 'description', 'description')
            ->addFieldResult('i', 'format', 'format');
        $query = $this->_em->createNativeQuery($customSQL, $this->resultSetMapping);
        // Use parameters to prepare the query
        $query->setParameter(1, $init)
            ->setParameter(2, $order)
            ->setParameter(3, MediaType::TYPE_CHOICES['trickThumbnail'])
            ->setParameter(4, $start)
            ->setParameter(5, $end);
        $results = $query->getResult();
        $count = \count($results);
        $tricks = [];
        // Rearrange results array to loop easily in template with rank
        for ($i = 0; $i < $count; $i ++) {
            $tricks[$i] = $results[$i][0];
            $tricks[$i]->assignRank($results[$i]['rank']);
        }
        // Returns an array of objects with tricks data
        return $tricks;
    }

    /**
     * Find a Trick entity with query based on its uuid.
     *
     * @param UuidInterface $uuid
     *
     * @return Trick|null
     *
     * @see: binary string to store uuid
     * https://mysqlserverteam.com/storing-uuid-values-in-mysql-tables/
     * @see: convert hex to bin and bin to hex
     * https://stackoverflow.com/questions/28251144/inserting-and-selecting-uuids-as-binary16
     * @see: binary string shortcut
     * https://docs.benramsey.com/ramsey-uuid/3.7.3/Ramsey/Uuid/Uuid.html#method_getBytes
     * @see: use of string codec instead of uuid:
     * https://github.com/ramsey/uuid/blob/29fb62b48611761b4c0c4e8f4a428cad19c2b690/src/Codec/StringCodec.php#L61-L100
     */
    public function findOneByUuid(UuidInterface $uuid) : ?Trick
    {
        // TODO: complete query later with users messages or use Message Repository to limit result!
        $queryBuilder = $this->createQueryBuilder('t' );
        // No need to join media_types and trick_groups due to no particular filter on data
        // trick group and media type data is added automatically thanks to lazy loading!
        // Specifying joins reduces query numbers!
        $result = $queryBuilder
            ->select(['t', 'tg', 'm', 'mt', 'i', 'v'])
            ->leftJoin('t.trickGroup', 'tg', 'WITH', 't.trickGroup = tg.uuid')
            ->leftJoin('t.medias', 'm', 'WITH', 'm.trick = t.uuid')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            ->leftJoin('m.image', 'i', 'WITH', 'm.image = i.uuid')
            ->leftJoin('m.video', 'v', 'WITH', 'm.video = v.uuid')
            ->where('t.uuid = ?1')
            ->orderBy('i.creationDate', 'DESC')
            ->addOrderBy('v.creationDate', 'DESC')
            ->setParameter(1, $uuid->getBytes())
            ->getQuery()
            ->getResult()
        ;
        if(empty($result)) {
            return null;
        }
        return $result[0];
    }
}
