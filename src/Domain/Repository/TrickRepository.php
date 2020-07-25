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
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @var Security
     */
    private $security;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * @var UserInterface|null
     */
    private $currentUser;

    /**
     * @var string
     */
    private $currentUserAuthenticationState;

    /**
     * TrickRepository constructor.
     *
     * @param RegistryInterface       $registry
     * @param ResultSetMappingBuilder $resultSetMapping
     * @param Security                $security
     * @param UserManager             $userService
     */
    public function __construct(
        RegistryInterface $registry,
        ResultSetMappingBuilder $resultSetMapping,
        Security $security,
        UserManager $userService
    )
    {
        parent::__construct($registry, Trick::class);
        // ResultSetMappingBuilder extends ResultSetMapping.
        $this->resultSetMapping = $resultSetMapping;
        $this->security = $security;
        $this->userService = $userService;
        // Get authenticated user (can be null)
        $this->currentUser = $this->security->getUser();
        // Store user authentication state as a kind of permissions information
        $this->currentUserAuthenticationState = $this->userService->getUserAuthenticationState();
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
        // Count all tricks when current user is authenticated
        /** @var User|UserInterface $user */
        if ($this->currentUser) {
            $user = $this->currentUser;
            $roles = $user->getRoles();
            switch ($roles) {
                // Current user is authenticated and is a simple member ("ROLE_ADMIN").
                case  \in_array(User::ADMIN_ROLE, $user->getRoles()):
                    return $this->countAllForAuthenticatedAdmin($queryBuilder);
                // Current user is authenticated and is a simple member ("ROLE_USER").
                case !\in_array(User::ADMIN_ROLE, $user->getRoles()):
                    return $this->countAllForAuthenticatedMember($queryBuilder);
            }
        }
        // Count only published tricks for anonymous (not authenticated) users
        return (int) $queryBuilder
            ->select($queryBuilder->expr()->count('t.uuid'))
            ->where('t.isPublished = 1')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all published tricks and unpublished tricks
     * for a particular authenticated administrator.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function countAllForAuthenticatedAdmin(QueryBuilder $queryBuilder) : int
    {
        // Return query result without filtering tricks moderation (published) state
        return (int) $queryBuilder
            ->select($queryBuilder->expr()->count('t.uuid'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all published tricks and unpublished tricks
     * for a particular authenticated simple member.
     *
     * @param QueryBuilder  $queryBuilder
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function countAllForAuthenticatedMember(QueryBuilder $queryBuilder) : int
    {
        /** @var UuidInterface $userUuid */
        $userUuid =  $this->currentUser->getUuid();
        // Prepare unpublished tricks filter for user
        $and = $queryBuilder->expr()->andX();
        $and->add($queryBuilder->expr()->eq('t.isPublished', '0'));
        $and->add($queryBuilder->expr()->eq('t.user', '?1'));
        // Return query result
        return (int) $queryBuilder
            ->select($queryBuilder->expr()->count('t.uuid'))
            ->where('t.isPublished = 1')
            ->orWhere($and)
            ->setParameter(1, $userUuid->getBytes())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find tricks expected data with query based on their user author uuid.
     *
     * @param UuidInterface $userUuid
     *
     * @return mixed
     */
    public function findAllByAuthor(UuidInterface $userUuid) : array
    {
        $queryBuilder = $this->createQueryBuilder('t');
        $result = $queryBuilder
            // IMPORTANT! This retrieves expected data correctly!
            ->select('u.uuid, t.uuid, t.name, t.slug')
            ->join('t.user', 'u', 'WITH', 't.user = u.uuid')
            ->where('u.uuid = ?1')
            ->orderBy('t.creationDate', 'DESC')
            ->setParameter(1, $userUuid->getBytes())
            ->getQuery()
            ->getResult();
        return $result;
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
     * @see CASE in WHERE clause:
     * https://stackoverflow.com/questions/14614573/using-case-in-the-where-clause
     * @see Dynamic WHERE with prepared statements:
     * https://phpdelusions.net/pdo_examples/dynamical_where
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
        // Get a native query thanks to a ResultSetMappingBuilder instance
        $customQuery = $this->getTrickListCustomNativeQuery($customSQL, $this->resultSetMapping);
        // Get this state to adapt query depending on user permissions
        $currentUserAuthenticationState = $this->currentUserAuthenticationState;
        // Use parameters to prepare the query and get result(s)
        $customQuery
            ->setParameter('userAuthenticationState', $currentUserAuthenticationState)
            ->setParameter('initRank', $init)
            ->setParameter('sortDirection', $order)
            ->setParameter('mediaType', MediaType::TYPE_CHOICES['trickThumbnail'])
            ->setParameter('startRank', $start)
            ->setParameter('endRank', $end);
        // Set current user uuid parameter if he is authenticated!
        /** @var UuidInterface $userUuid */
        $userUuid = !\is_null($this->currentUser) ? $this->currentUser->getUuid() : null;
        $userUuidBinary =  !\is_null($userUuid) ? $userUuid->getBytes() : null;
        $customQuery->setParameter('userUuid', $userUuidBinary);
        // Get query result;
        $result = $customQuery->getResult();
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
     * Find a Trick entity with query based on its uuid to show data on a single page.
     *
     * Please note only one query is used to get all needed Trick data on single page.
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
    public function findOneToShowByUuid(UuidInterface $uuid) : ?Trick
    {
        // TODO: complete query later with users messages or use Message (Comment) Repository to limit result!
        $queryBuilder = $this->createQueryBuilder('t');
        // Specifying joins reduces query numbers!
        $result = $queryBuilder
            // IMPORTANT! This feeds all objects properties correctly!
            ->select(['t', 'tg','mo', 'm', 'mt', 'ms', 'i', 'v'])
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
     * Find a Trick entity with query based on its uuid to update data in form.
     *
     * Please note only one query is used to get all needed Trick data on single page.
     *
     * @param UuidInterface $uuid
     *
     * @return Trick|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneToUpdateInFormByUuid(UuidInterface $uuid) : ?Trick
    {
        $queryBuilder = $this->createQueryBuilder('t');
        // Specifying joins reduces query numbers!
        $result = $queryBuilder
            // IMPORTANT! This feeds all objects properties correctly!
            ->select(['t', 'tg', 'u', 'mo', 'm', 'mt', 'ms', 'i', 'v'])
            ->leftJoin('t.trickGroup', 'tg', 'WITH', 't.trickGroup = tg.uuid')
            ->leftJoin('t.user', 'u', 'WITH', 't.user = u.uuid')
            ->leftJoin('t.mediaOwner', 'mo', 'WITH', 'mo.trick = t.uuid')
            ->leftJoin('mo.medias', 'm', 'WITH', 'm.mediaOwner = mo.uuid')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            ->leftJoin('m.mediaSource', 'ms', 'WITH', 'm.mediaSource = ms.uuid')
            ->leftJoin('ms.image', 'i', 'WITH', 'ms.image = i.uuid')
            ->leftJoin('ms.video', 'v', 'WITH', 'ms.video = v.uuid')
            // Expression is equivalent to 't.uuid= ?1' in WHERE clause.
            ->where($queryBuilder->expr()->eq('t.uuid', '?1'))
            // Each group of medias sorted earlier by types are sorted by show list rank data.
            ->addOrderBy('m.showListRank', 'ASC')
            ->setParameter(1, $uuid->getBytes())
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
                    SELECT t.uuid, t.name AS t_name, t.slug, t.is_published, t.creation_date,
                           u.uuid AS u_uuid,
                           tg.uuid AS tg_uuid, tg.name AS tg_name,
                           mo.uuid AS mo_uuid,
                           m.uuid AS m_uuid,
                           mt.uuid AS mt_uuid, mt.type,
                           ms.uuid AS ms_uuid,
                           i.uuid AS i_uuid, i.name AS i_name, i.description, i.format
                    FROM
                    (
                        -- Init current user authentication state to adapt filters
                        -- Init user variables with parameters using SELECT
                        -- @curRank is initialized with -1 (ASC) or tricks total count + 1 (DESC).
                        SELECT @userAuthenticationState := :userAuthenticationState,
                               @curRank := :initRank, 
                               @sortDirection := :sortDirection
                    ) AS s, 
                    tricks t
                    INNER JOIN users u ON t.user_uuid = u.uuid
                    INNER JOIN trick_groups tg ON t.trick_group_uuid = tg.uuid
                    INNER JOIN media_owners mo ON mo.trick_uuid = t.uuid 
                    INNER JOIN medias m ON m.media_owner_uuid = mo.uuid    
                    INNER JOIN media_types mt ON m.media_type_uuid = mt.uuid
                    INNER JOIN media_sources ms ON m.media_source_uuid = ms.uuid
                    INNER JOIN images i ON ms.image_uuid = i.uuid
                    -- Filter with media type
                    WHERE mt.type = :mediaType
                    -- Filter with image main status 
                    -- IMPORTANT: avoid issue by selecting, for each trick, only 1 one thumb image which is the main one 
                    AND m.is_main = 1
                    -- Filter with current user authentication state
                    AND
                    CASE WHEN @userAuthenticationState = '" . User::UNAUTHENTICATED_STATE . "' -- ANONYMOUS
                         THEN t.is_published = 1
                         WHEN @userAuthenticationState = '" . User::DEFAULT_ROLE . "' -- SIMPLE MEMBER
                         THEN t.is_published = 1 OR (t.user_uuid = :userUuid AND t.is_published = 0) 
                         WHEN @userAuthenticationState = '" . User::ADMIN_ROLE . "' -- ADMINISTRATOR 
                         THEN t.is_published = 1 OR t.is_published = 0 END   
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
            ->addFieldResult('t', 'is_published', 'isPublished')
            ->addFieldResult('t', 'creation_date', 'creationDate')
            ->addScalarResult('rank', 'rank', 'integer');
        $resultSetMapping->addJoinedEntityResult(User::class , 'u', 't', 'user')
            ->addFieldResult('u', 'u_uuid', 'uuid');
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
