<?php

namespace MOJElasticSearch;

define('DEBUG_ECHO', true);

class Query extends ElasticSearch
{
    public $s_query = '';
    public $s_is_empty = true;
    public $ids = [];

    public function __construct()
    {
        $this->search();
        $this->actions();
        parent::__construct();
    }

    public function actions()
    {
        add_action('pre', [$this, 'setIDs'], 9, 1);
    }

    public function search()
    {
        if ($this->isSearch()) {
            $raw_search = $this->s_query;
            $result = $this->client()->search($this->_setParams($raw_search));

            $this->ids = $this->_ids($result);
            //Debug::this('Elasticsearch result...', $result);
        }

        // fix bug on Intranet where site crashes with empty query
        if ($this->s_is_empty) {
            unset($_GET['s']);
        }
    }

    private function _setParams($query)
    {
        return [
            'index' => ES_INDEX,
            'body' => [
                'query' => [
                    'match' => [
                        'post_content' => $query
                    ]
                ]
            ]
        ];
    }

    private function _ids($result)
    {
        $hits = $result['hits'] ?? [];
        $ids = [];
        if (!empty($hits)) {
            $total = $hits['total'] ?? 0;
            foreach ($hits['hits'] as $hit) {
                $ids[] = $hit['_source']['ID'];
            }
        }
        return $ids;
    }

    public function isSearch()
    {
        $this->s_query = $_GET['s'] ?? '';
        $this->s_is_empty = empty($this->s_query);
        return !$this->s_is_empty;
    }

    public function setIDs($where)
    {
        /*$ids = implode(",", $this->ids);
        $where = " AND wp_posts.ID IN ('{$ids}') ";
        remove_all_actions('__after_loop');
        return $where;*/
        if (!empty($this->ids) && $query->is_search()) {
            $query->set('post__in', $this->ids);
            $query->set('s', null);
            Debug::this('', $query);
            $this->ids = [];
            remove_all_actions('__after_loop');
        }
    }
}
