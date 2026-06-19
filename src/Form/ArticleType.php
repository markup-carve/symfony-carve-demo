<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Body (Carve markup)',
                'attr' => ['rows' => 12],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Comment (optional, Carve markup)',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('render', SubmitType::class, [
                'label' => 'Render',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
            'csrf_protection' => false,
        ]);
    }
}
