<?php

namespace App\Controller\Admin;

use App\Controller\EasyAdmin\VotesField;
use App\Entity\Question;
use App\Entity\User;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_MODERATOR')]
class QuestionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Question::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort(['askedBy.enabled' => 'DESC', 'createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::INDEX, 'ROLE_MODERATOR')
            ->setPermission(Action::DETAIL, 'ROLE_MODERATOR')
            ->setPermission(Action::EDIT, 'ROLE_MODERATOR')
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),
            Field::new('slug')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', $pageName !== Crud::PAGE_NEW),
            Field::new('name')
                ->setSortable(false),
            AssociationField::new('topic'),
            TextareaField::new('question')
                ->hideOnIndex()
                ->setFormTypeOptions([
                    'row_attr' => [
                        'data-controller' => 'snarkdown',
                    ],
                    'attr' => [
                        'data-snarkdown-target' => 'input',
                        'data-action' => 'snarkdown#render'
                    ],
                ])
                ->setHelp('Preview:'),
            VotesField::new('votes', 'Total Votes')
                ->setPermission('ROLE_SUPER_ADMIN'),
            AssociationField::new('askedBy')
                ->autocomplete()
                ->formatValue(static function ($value, ?Question $question) {
                    if (!$user = $question?->getAskedBy()) {
                        return null;
                    }

                    return sprintf('%s&nbsp;(%s)', $user->getEmail(), $user->getQuestions()->count());
                })
                ->setQueryBuilder(function (QueryBuilder $queryBuilder) {
                    $queryBuilder->andWhere('entity.enabled = :enabled')
                        ->setParameter('enabled', true);
                }),
            AssociationField::new('answers')
                ->autocomplete()
                ->setFormTypeOption('by_reference', false),
            Field::new('createdAt')
                ->hideOnForm(),
            AssociationField::new('updatedBy')
                ->onlyOnDetail(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add('topic')
            ->add('createdAt')
            ->add('votes')
            ->add('name');
    }

    // This method is called before persisting and updating entity instances
    // It do the same as BlameableSubscriber but in a more elegant way
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Currently logged in user is not a User instance');
        }

        $entityInstance->setUpdatedBy($user);

        parent::updateEntity($entityManager, $entityInstance);
    }
}
