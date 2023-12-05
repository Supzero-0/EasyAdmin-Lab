<?php

namespace App\Controller\Admin;

use App\Controller\EasyAdmin\VotesField;
use App\Entity\Question;
use App\Entity\User;
use App\Service\CsvExporter;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RequestStack;

#[IsGranted('ROLE_MODERATOR')]
class QuestionCrudController extends AbstractCrudController
{
    private AdminUrlGenerator $adminUrlGenerator;
    private RequestStack $requestStack;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, RequestStack $requestStack)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }


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
        $viewAction = function () {
            return Action::new('view')
                ->linkToUrl(function (Question $question) {
                    return $this->generateUrl('app_question_show', [
                        'slug' => $question->getSlug(),
                    ]);
                })
                ->setIcon('fa fa-eye')
                ->setLabel('View on site')
                ->displayIf(static function (Question $question): bool {
                    return $question->getIsApproved();
                });
        };

        $approvedAction = Action::new('approve')
            ->addCssClass('btn btn-success')
            ->setIcon('fa fa-check-circle')
            ->displayAsButton()
            ->setTemplatePath('admin/approve_action.html.twig')
            ->linkToCrudAction('approve')
            ->displayIf(static function (Question $question): bool {
                return !$question->getIsApproved();
            });

        $exportAction = Action::new('export')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();

                return $this->adminUrlGenerator
                    ->setAll($request->query->all())
                    ->setAction('export')
                    ->generateUrl();
            })
            ->addCssClass('btn btn-success')
            ->setIcon('fa fa-download')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                $action->displayIf(static function (Question $question) {
                    return !$question->getIsApproved();
                });

                return $action;
            })
            ->setPermission(Action::INDEX, 'ROLE_MODERATOR')
            ->setPermission(Action::DETAIL, 'ROLE_MODERATOR')
            ->setPermission(Action::EDIT, 'ROLE_MODERATOR')
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->disable(Action::BATCH_DELETE)
            ->add(Crud::PAGE_DETAIL, $viewAction()->addCssClass('btn btn-success'))
            ->add(Crud::PAGE_INDEX, $viewAction())
            ->add(Crud::PAGE_DETAIL, $approvedAction)
            ->add(Crud::PAGE_INDEX, $exportAction);
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

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getIsApproved()) {
            throw new \Exception('Deleting approved questions is forbidden');
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function approve(AdminContext $adminContext, EntityManagerInterface $entityManager, AdminUrlGenerator $adminUrlGenerator)
    {
        $question = $adminContext->getEntity()->getInstance();
        if (!$question instanceof Question) {
            throw new \LogicException('Entity is missing or not a Question');
        }

        $question->setIsApproved(true);
        $entityManager->flush();

        $targetUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($question->getId())
            ->generateUrl();

        return $this->redirect($targetUrl);
    }

    public function export(AdminContext $context, CsvExporter $csvExporter)
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $context->getCrud()->setFieldAssets($this->getFieldAssets($fields));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, $fields, 'questions.csv');
    }
}
