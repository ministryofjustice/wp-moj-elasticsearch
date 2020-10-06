<?php

namespace MOJElasticSearch;

use stdClass;

class Alias
{
    use Debug;

    private $url = '';
    public $name = '';

    public function __construct()
    {
        $this->url = get_option('ep_host') . '_aliases';
        $this->name = get_option('_moj_es_alias_name');
    }

    /**
     * @param $index
     * @return string
     */
    public function add($index)
    {
        $body = '{"actions":[{"add":{"index":"' . $index . '","alias":"' . $this->name . '"}}]}';

        echo $this->debug('HOST', $this->url);
        echo $this->debug('A L I A S', $this->name);
        echo $this->debug('B O D Y', $body);

        return wp_remote_post($this->url, ['body' => $body]);
    }

    /**
     * @param $add
     * @param $remove
     * @return string
     */
    public function update($add, $remove)
    {
        $body = '{"actions":[{"remove":{"index":"' . $remove . '","alias":"' . $this->name . '"}},{"add":{"index":"' . $add . '","alias":"' . $this->name . '"}}]}';

        echo $this->debug('A L I A S', $this->name);
        echo $this->debug('B O D Y', $body);

        update_option('_moj_es_index_name', $add);

        return wp_remote_post($this->url, ['body' => $body]);
    }
}
