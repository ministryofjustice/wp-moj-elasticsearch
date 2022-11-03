<?php

namespace MOJElasticSearch;

/**
 * Class Index
 * @package MOJElasticSearch
 * @SuppressWarnings(PHPMD)
 */
class Index
{
    /**
     * This class requires settings fields in the plugins dashboard.
     * Include the Settings trait
     */
    use Debug;

    /**
     * Current Index name
     * @var string|null name
     */
    public $index_name_current = '';


    public function __construct()
    {
        $this->index_name_current = get_option('_moj_es_current_index_name');

        self::hooks();
    }

    /**
     * A place for all class specific hooks and filters
     */
    public function hooks()
    {
        add_filter('ep_index_name', [$this, 'indexNames'], 11, 1);
        add_filter('ep_prepare_meta_data', [$this, 'limitACFMeta']);
        add_filter('ep_prepare_meta_excluded_public_keys', [$this, 'excludeMeta']);
    }


    /**
     * This method returns the current index name
     *
     * @param string $index_name
     * @return string
     */
    public function indexNames(string $index_name): string
    {
        if (!empty($this->index_name_current)) {
            return $this->index_name_current;
        } else {
            return $this->getNewIndexName();
        }
    }

    /**
     * This method generates a random index name then saves it in the DB as the current index name
     * Hooked into filter 'ep_index_name' via $this->indexNames()
     *
     * @return string
     */
    private function getNewIndexName(): string
    {
        // index names
        $index_names = [
            'mythical' => [
                'afanc', 'alphyn', 'amphiptere', 'basilisk', 'bonnacon', 'cockatrice', 'crocotta', 'dragon', 'griffin',
                'hippogriff', 'mandragora', 'manticore', 'melusine', 'ouroboros', 'salamander', 'woodwose'
            ],
            'knight' => [
                'bagdemagus', 'bedivere', 'bors', 'brunor', 'cliges', 'caradoc', 'dagonet', 'daniel', 'dinadan',
                'galahad', 'galehaut', 'geraint', 'griflet', 'lamorak', 'lancelot', 'lanval', 'lionel', 'moriaen',
                'palamedes', 'pelleas', 'pellinore', 'percival', 'sagramore', 'tristan'
            ]
        ];

        $new_index_names = array_rand($index_names); // string
        $new_index_key = array_rand($index_names[$new_index_names]); // int
        $new_index = $index_names[$new_index_names][$new_index_key]; // string

        $namespace = (function_exists('env') ? env('ES_INDEX_NAMESPACE') : null);
        $new_index = ($namespace ? $namespace . "." : "") . $new_index;

        $site_url = get_site_url();

        if (str_contains($site_url, 'docker')) {
            $new_index = 'local.' . $new_index;
        } elseif (str_contains($site_url, 'dev.wp.dsd.io')) {
            $new_index = 'dev.' . $new_index;
        } elseif (str_contains($site_url, 'staging.wp.dsd.io')) {
            $new_index = 'staging.' . $new_index;
        } else {
            $new_index = 'prod.' . $new_index;
        }

        update_option('_moj_es_current_index_name', $new_index);

        return $new_index;
    }

    public function limitACFMeta($meta): array
    {
        foreach ($meta as $key => $value) {
            if (preg_match('/.+_([0-9]+)_.+/', $key)) {
                unset($meta[$key]);
            }
        }

        return $meta;
    }

    public function excludeMeta(): array
    {
        return [
            "built_in_post_types_event", "built_in_post_types_task", "built_in_post_types_projects",
            "built_in_post_types_vacancies", "built_in_post_types_glossaryitem", "built_in_taxonomies_category",
            "built_in_taxonomies_post_tag", "built_in_taxonomies_link_category", "built_in_taxonomies_grade",
            "built_in_taxonomies_team", "built_in_taxonomies_document_type", "built_in_taxonomies_atoz",
            "built_in_taxonomies_agency", "built_in_taxonomies_author", "built_in_taxonomies_dw_snippet",
            "built_in_taxonomies_snippet_categories", "built_in_taxonomies_workflow_state", "built_in_taxonomies_news_category",
            "built_in_taxonomies_resource_category", "built_in_post_types_news"
        ];
    }
}
