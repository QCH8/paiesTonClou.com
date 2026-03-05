<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Model\ProductSearch;

class ProductSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', TextType::class,[
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'Rechercher...'],
            ])
            ->add('sku', TextType::class,[
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'SKU...'],
            ])
            ->add('minPriceHT', IntegerType::class,[
                'required' => false,
                'label'=> false,
                'attr' => ['placeholder' => 'Min €']
            ])
            ->add('maxPriceHT', IntegerType::class,[
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'Max €'],
            ])
            ->add('category', EntityType::class,[
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'label'=> false,
                'placeholder' => 'Toutes catégories'
            ])
            ->add('activeOnly', CheckboxType::class, [
                'required' => false,
                'label' => 'Produits actifs uniquement',
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'=> ProductSearch::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
