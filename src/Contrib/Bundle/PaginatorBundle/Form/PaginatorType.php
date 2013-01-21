<?php

namespace Contrib\Bundle\PaginatorBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\HttpFoundation\Request;

class PaginatorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('page', null)
        ->add('size', null)
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $defaultValues = array(
            'data_class' => 'Contrib\Bundle\PaginatorBundle\Form\Model\Paginator',
        );

        $resolver->setDefaults($defaultValues);
    }

    public function getName()
    {
        return '';
    }

    public static function filterRequest(Request $request, array $default = array())
    {
        $default += array(
            'page' => 1,
            'size' => 20,
        );

        return array(
            'page' => $request->get('page', $default['page']),
            'size' => $request->get('size', $default['size']),
        );
    }
}
