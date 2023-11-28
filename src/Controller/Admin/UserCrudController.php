<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),
            TextField::new('fullName')
                ->hideOnForm(),
            TextField::new('firstName')
                ->onlyOnForms(),
            TextField::new('lastName')
                ->onlyOnForms(),
            EmailField::new('email'),
            BooleanField::new('enabled')
                ->renderAsSwitch(false),
            DateField::new('createdAt')
                ->hideOnForm(),
        ];
    }
}
