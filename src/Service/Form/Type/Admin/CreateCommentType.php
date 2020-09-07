<?php

declare(strict_types=1);

namespace App\Service\Form\Type\Admin;

use App\Domain\DTO\CreateCommentDTO;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\CommentManager;
use App\Utils\Traits\UuidHelperTrait;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CreateCommentType.
 *
 * Build a trick comment creation form type.
 */
class CreateCommentType extends AbstractType
{
    use UuidHelperTrait;

    /**
     * @var CommentManager
     */
    private $commentService;

    /**
     * CreateCommentType constructor.
     *
     * @param CommentManager $commentService
     */
    public function __construct(CommentManager $commentService)
    {
        $this->commentService = $commentService;
    }

    /**
     * Configure a form builder for the type hierarchy.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @return void
     *
     * @see https://chrisguitarguy.com/2018/10/05/symfony-choice-type-error-messages/
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $commentService = $this->commentService;
        // init an index for comment loop
        $commentIndex = 0;
        $currentTrick = $options['trickToUpdate'];
        $builder
            ->add('content', TextareaType::class, [
            ])
            ->add('token', HiddenType::class, [
                'inherit_data' => true
            ]);
        // Enable reply field if at least a previous comment was posted for current trick
        if (\count($currentTrick->getComments()) !== 0) {
            $builder
                ->add('parentComment', EntityType::class, [
                    'class'           => Comment::class,
                    // Order comments by creation date ascending order when feeding select
                    'query_builder'   => function () use ($commentService, $currentTrick) {
                        /** @var UuidInterface $currentTrickUuid */
                        $currentTrickUuid = $currentTrick->getUuid();
                        return $commentService->getRepository()
                            ->createQueryBuilder('c')
                            ->join('c.trick', 't', 'WITH', 'c.trick = t.uuid')
                            ->where('t.uuid = ?1')
                            ->orderBy('c.creationDate', 'ASC')
                            ->setParameter(1, $currentTrickUuid->getBytes());
                    },
                    // Show group names in select
                    'choice_label'    => function (Comment $comment) use (&$commentIndex) {
                        $commentIndex++;
                        $userFullName = $comment->getUser()->getFirstName() . ' ' .$comment->getUser()->getFamilyName();
                        /** @var Comment $comment */
                        return "Comment #{$commentIndex} posted by " . $userFullName .
                               ' (' . $comment->getUser()->getNickName() . ')' .
                               ' added on ' . $comment->getCreationDate()->format('d/m/Y');
                    },
                    // Use encoded uuid value to query entities
                    // Replace the need to use a setter for "parentComment" corresponding CreateCommentDTO property
                    'choice_value'    => function (Comment $comment = null) {
                        return !\is_null($comment) ? $this->encode($comment->getUuid()) : '';
                    },
                    'placeholder'     => 'No reply',
                    'invalid_message' => "You are not allowed to tamper\nparent comments choice list!"
                ]);
        }
    }

    /**
     * Configure options for this form type.
     *
     * @param OptionsResolver $resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'     => CreateCommentDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new CreateCommentDTO(
                    // "null" by default if no previous comment was posted (Select field is not available!)
                    $form->offsetExists('parentComment')
                        ? $form->get('parentComment')->getData(): null,
                    $form->get('content')->getData()
                );
            },
            'required'        => false,
            // Disable automatic CSRF validation: this validation/protection is checked/done in form handler manually!
            'csrf_protection' => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'create_comment_token',
        ]);
        // Check "trickToUpdate" option
        $resolver->setRequired('trickToUpdate');
        $resolver->setAllowedValues('trickToUpdate', function ($value) {
            if (!$value instanceof Trick) {
                return false;
            }
            return true;
        });
    }
}
