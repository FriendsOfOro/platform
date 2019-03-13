<?php

namespace Oro\Bundle\EmailBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\FormBundle\Autocomplete\SearchRegistry;
use Oro\Bundle\FormBundle\Form\DataTransformer\EntityCreationTransformer;
use Oro\Bundle\FormBundle\Form\DataTransformer\EntityToIdTransformer;
use Oro\Bundle\FormBundle\Form\Type\OroJquerySelect2HiddenType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EntityRecordSelectType extends AbstractType
{
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    /** @var ConfigManager */
    protected $configManager;

    /** @var EntityManager */
    protected $entityManager;

    /** @var SearchRegistry */
    protected $searchRegistry;

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param ConfigManager                 $configManager
     * @param EntityManager                 $entityManager
     * @param SearchRegistry                $searchRegistry
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        ConfigManager $configManager,
        EntityManager $entityManager,
        SearchRegistry $searchRegistry
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->configManager = $configManager;
        $this->entityManager = $entityManager;
        $this->searchRegistry = $searchRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return OroJquerySelect2HiddenType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'new_item_property_name'        => null,
                'new_item_allow_empty_property' => false,
                'new_item_value_path'           => 'value',
                'configs'            => [
                    'placeholder'             => 'oro.user.form.choose_user',
                    'result_template_twig'    => 'OroEmailBundle:EmailTemplate:Autocomplete/result.html.twig',
                    'selection_template_twig' => 'OroEmailBundle:EmailTemplate:Autocomplete/selection.html.twig'
                ]
            ]
        );

        $resolver->setNormalizer(
            'transformer',
            function (Options $options, $value) {
                if (!$value && !empty($options['entity_class'])) {
                    $value = new EntityToIdTransformer(
                        $this->entityManager,
                        $options['entity_class']
                    );
                }

                if (!$value instanceof DataTransformerInterface) {
                    throw new TransformationFailedException(
                        sprintf(
                            'The option "transformer" must be an instance of "%s".',
                            'Symfony\Component\Form\DataTransformerInterface'
                        )
                    );
                }

                return $value;
            }
        );

        $this->setConfigsNormalizer($resolver);
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function setConfigsNormalizer(OptionsResolver $resolver)
    {
        $resolver->setNormalizer(
            'configs',
            function (Options $options, $configs) {
                if (!empty($options['autocomplete_alias'])) {
                    $autoCompleteAlias            = $options['autocomplete_alias'];
                    $configs['autocomplete_alias'] = $autoCompleteAlias;
                    if (empty($configs['properties'])) {
                        $searchHandler        = $this->searchRegistry->getSearchHandler($autoCompleteAlias);
                        $configs['properties'] = $searchHandler->getProperties();
                    }
                    if (empty($configs['route_name'])) {
                        $configs['route_name'] = 'oro_form_autocomplete_search';
                    }
                    if (empty($configs['component'])) {
                        $configs['component'] = 'autocomplete';
                    }
                }

                if (!array_key_exists('route_parameters', $configs)) {
                    $configs['route_parameters'] = [];
                }

                if (empty($configs['route_name'])) {
                    throw new InvalidConfigurationException(
                        'Option "configs[route_name]" must be set.'
                    );
                }

                return $configs;
            }
        );
    }
}
