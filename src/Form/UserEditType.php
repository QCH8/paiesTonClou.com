<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locked = (bool) $options['locked'];
        $inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm';
        $editableAttr = [
            'class' => $inputClass,
            'data-profile-editable' => '1',
        ];
        if ($locked) {
            $editableAttr['readonly'] = 'readonly';
        }

        $memberSince = '';
        $data = $builder->getData();
        if ($data instanceof User && $data->getCreatedAt() !== null) {
            $memberSince = $data->getCreatedAt()->format('d/m/Y H:i');
        }

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => $editableAttr,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => [
                        'class' => $inputClass,
                        'data-profile-editable' => '1',
                        'disabled' => $locked,
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'class' => $inputClass,
                        'data-profile-editable' => '1',
                        'disabled' => $locked,
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => [
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('createdAtLabel', TextType::class, [
                'label' => 'Membre depuis',
                'mapped' => false,
                'data' => $memberSince,
                'disabled' => true,
                'attr' => [
                    'class' => $inputClass,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'locked' => true,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'user_edit',
        ]);

        $resolver->setAllowedTypes('locked', 'bool');
    }
}
