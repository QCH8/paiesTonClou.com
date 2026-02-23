<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200';

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'autocomplete' => 'email',
                    'class' => $inputClass,
                ],
            ])

            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => $inputClass,
                ],
                'constraints' => [
                    new NotBlank( message: 'Please enter a password' ),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'Your password should be at least {{ limit }} characters',
                    ),
                ],
            ])

            ->add('agreeTerms', CheckboxType::class, [
                'label' => "J'accepte les conditions",
                'mapped' => false,
                'attr' => [
                    'class' => 'h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-300',
                ],
                'constraints' => [
                    new IsTrue( message: 'You should agree to our terms.' ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
