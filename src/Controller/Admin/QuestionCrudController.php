<?php

namespace App\Controller\Admin;

use App\Controller\EasyAdmin\VotesField;
use App\Entity\Question;
use Doctrine\DBAL\Query\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

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
                ->hideOnIndex(),
            VotesField::new('votes', 'Total Votes'),
            AssociationField::new('askedBy')
                ->autocomplete()
                ->formatValue(static function ($value, Question $question) {
                    if (!$user = $question->getAskedBy()) {
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
        ];
    }
}
