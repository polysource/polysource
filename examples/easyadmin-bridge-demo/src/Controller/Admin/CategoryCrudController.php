<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Polysource\Demo\EasyAdminBridge\Entity\Category;

final class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    /**
     * @return iterable<\EasyCorp\Bundle\EasyAdminBundle\Field\FieldInterface>
     */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name');
    }
}
