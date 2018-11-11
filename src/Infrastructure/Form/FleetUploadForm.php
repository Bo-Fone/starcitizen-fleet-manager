<?php

namespace App\Infrastructure\Form;

use App\Infrastructure\Form\Dto\FleetUpload;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FleetUploadForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('handleSC', TextType::class, [])
            ->add('fleetFile', FileType::class, []);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FleetUpload::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false,
        ]);
    }
}