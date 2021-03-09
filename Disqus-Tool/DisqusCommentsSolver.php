<?php
    /**
     * @author  Shawn.yao
     * @version version 2.0
     *
     */

    error_reporting(E_ALL);
    set_time_limit(0);
    require_once "wp-config.php";
    require_once "DisqusData.php";

    echo "FBI WARNING! EasyWare WARNING! EarlyBird WARNING! \n";
    echo "====================================== \n";
    echo "This Option Will Modify The these data table of DataBase \n";
    echo "DataTable: wp_posts \n";
    echo "DataTable: wp_comments \n";
    echo "DataTable: wp_commentmeta \n";
    echo "====================================== \n";
    echo "So, Please Be Sure Backup your Database Before You start! \n";
    echo "If Not, you'd better enter 'ctrl + c' to terminate this Script \n";
    sleep(10);

    define('SHORT_NAME', ''); #Disqus Short Name / Disqus Id
    define('API_PUBLIC_KEY', ''); #Disqus Public key check the disqus plugin setting in your wp site;
    define('SITE_PREFIX', ''); #yourSite link e.g. https://www.myblog.com
    define('DISQUS_GET_COMMENTS', 'http://disqus.com/api/3.0/posts/list.json?api_key=' . API_PUBLIC_KEY . '&thread=');
    define('DISQUS_GET_THREAD_ID', 'http://disqus.com/api/3.0/threads/list.json?api_key=' . API_PUBLIC_KEY . '&forum=' . SHORT_NAME . '&thread=link:');
    global $wpdb;
    $sql_get_disqus_id = "SELECT meta_value FROM wp_commentmeta WHERE comment_id = %d AND meta_key = 'dsq_post_id'";
    $sql_get_post_link = 'SELECT ID, post_name FROM wp_posts WHERE post_status = "publish" and post_type="post"';
    $sql_get_all_disqus_id = "SELECT meta_value FROM wp_commentmeta WHERE meta_key = 'dsq_post_id'";
    $ids_arr = array();
    $comments_from_disqus = array();
    $local_json_store = array();
    $solved_list = array();

    $posts = $wpdb->get_results($sql_get_post_link);
    $all_disqus_post_id = $wpdb->get_results($sql_get_all_disqus_id);

    echo "Get Comments Info From Disqus By CURL, Start Now \n";
    echo "=================================================\n";

    foreach ($posts as $k) {
        try {
            $thread_id = curlGet(DISQUS_GET_THREAD_ID . SITE_PREFIX . $k->post_name . '/');
            $comments = curlGet(DISQUS_GET_COMMENTS . $thread_id->response[0]->id);
        } catch (Exception $e) {
            echo $e->getMessage() . '\n';
            die("Thread Requset Failed, Please Check your Net Connection \n");
        }
        if (count($comments->response) == 0) continue;

        $post_id = (int)explode(' ', $thread_id->response[0]->identifiers[0])[0];  # post_id
        echo "Get Comments Of SITE_PREFIX . $k->post_name \n";
        $comments_from_disqus [] = new DisqusData($post_id, $thread_id->response[0]->id, $comments->response, $wpdb);
    }

    echo "Get Comments Info From Disqus By CURL,     End   \n";
    echo "=================================================\n";
    echo "Solve Comments Begin! \n";
    echo "=================================================\n";

    foreach ($comments_from_disqus as $thread) {
        for ($i = $thread->comments_count - 1; $i >= 0; $i--) {
            $solved_list[] = $thread->solveComment($i);
        }
    }
    try {
        #删除重复的评论数据
        $comments_from_disqus[0]->deleteComment($solved_list);
        #对数据库评论总数 重新进行计数
        $comments_from_disqus[0]->reCountComment();
    } catch (Exception $e) {
        echo "It seems That All your article has No comments in Disqus. Sad -_-! \n";
        die;
    }

    echo "Script Runs Over~ \n";
    sleep(5);
    echo "This Machine Will be Destroyed after 10 Seconds, Go Away! Now! \n";

    # API: http://disqus.com/api/3.0/posts/list.json?api_key=API_PUBLIC_KEY_HERE&thread=[thread id]
    function curlGet(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $comments = curl_exec($ch);
        curl_close($ch);
        return json_decode($comments);
    }




