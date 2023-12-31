<?php

namespace App\Controller\Admin;

use App\Controller\EasyAdmin\VotesField;
use App\Entity\Answer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class AnswerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Answer::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),
            Field::new('answer'),
            VotesField::new('votes'),
            AssociationField::new('question')
                ->autocomplete()
                ->setCrudController(QuestionCrudController::class)
                ->hideOnIndex(),
            AssociationField::new('answeredBy'),
            Field::new('createdAt')
                ->hideOnForm(),
            Field::new('updatedAt')
                ->onlyOnDetail(),
        ];
    }
}
