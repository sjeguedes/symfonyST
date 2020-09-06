<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Comment;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Class CommentFixtures.
 *
 * Generate trick comment fake starting set of data in database.
 */
class CommentFixtures extends BaseFixture implements DependentFixtureInterface
{
    /**
     * Get class dependencies with other entities.
     *
     * @return array
     */
    public function getDependencies() : array
    {
        return [
            TrickFixtures::class,
            UserFixtures::class
        ];
    }

    /**
     * VideoFixtures constructor.
     *
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        parent::__construct($parameterBag);
    }

    /**
     * Persist and flush entities in database.
     *
     * @param ObjectManager $manager
     *
     * @return void
     *
     * @throws \Exception
     */
    public function loadData(ObjectManager $manager) : void
    {
        $array = $this->parseYamlFile('comment_fixtures.yaml');
        $data = $array['comments'];
        $commentReferences = [];
        // Create trick videos
        $this->createFixtures(Comment::class, \count($data), function ($i) use ($data, &$commentReferences) {
            /** @var $proxy object|Trick */
            $proxy = $this->getReference(Trick::class . '_' . $data[$i]['references']['trick']);
            /** @var $proxy2 object|User */
            $proxy2 = $this->getReference(User::class . '_' . $data[$i]['references']['user']);
            $comment = new Comment(
                $proxy,
                $proxy2,
                $data[$i]['fields']['content'],
                null, // depends on existing comments
                new \DateTime(sprintf("+%d days", - \count($data) +$i)) // must be this to have a coherent list
            );
            // Store comments to retrieve possible parent comment or null for each created comment
            $commentReferences[$i + 1] = $comment;
            // Update necessary parent comment, if it is not null and after its creation!
            if (!\is_null($data[$i]['references']['parent_comment'])) {
                $parentComment = $commentReferences[$data[$i]['references']['parent_comment']];
                $comment->modifyParentComment($parentComment);
            }
            return $comment;
        });
        $manager->flush();
    }
}
