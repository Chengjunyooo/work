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





//
//    var_dump ( date_default_timezone_get ());
//    var_dump(date( 'Y-m-d H:i:s', strtotime('2019-1-23T16:8:34')) );
//    die;

//    $handel = fopen('comments.txt', 'w+');
//    file_put_contents($handel, $local_json_store);
//    fclose( $handel);

//    foreach( $all_disqus_post_id as $disqus_id ){
//        $ids_arr[] = $disqus_id->meta_value;
//    }

//    // 判断disqus post 是否已经同步到本地
//    foreach ($comments->response as $comment) {
//        if (!in_array($comment->id, $ids_arr)) {
//            echo "$comment->id \n";
//            echo "$comment->raw_message \n";
//            echo "update article comments " . SITE_PREFIX . $k->post_name . '/' . "\n";
//            $unsync_count++;
//        }else{
//            echo "Already sync \n";
//        }
//    }

//     重新更新各篇文章的评论数目
//        echo "Recount comments of articles \n";
//        // 计算 wp_comments 相同comment_post_id  的评论的数量
//        $sql = <<<SQL
//            UPDATE wp_posts AS posts1
//            right JOIN (
//                SELECT
//                    posts.ID,
//                    count( comments.comment_ID ) AS new_count
//                FROM
//                    wp_posts AS posts
//                    LEFT JOIN wp_comments AS comments ON posts.ID = comments.comment_post_id
//                WHERE
//                    posts.post_status = 'publish'
//                    AND comments.comment_type = 'comment'
//                GROUP BY
//                    posts.ID
//                ) AS new_cout on posts1.ID = new_cout.ID
//            SET posts1.comment_count = new_cout.new_count
//        SQL;

/*==================


        $sql_update_post_id =<<<SQL
UPDATE wp_comments 
SET comment_post_ID = $post_id
WHERE
	comment_ID = (
        SELECT comment_id 
        FROM wp_commentmeta 
        WHERE
            meta_key = "dsq_post_id" 
        AND meta_value = {$comment->id}
	);
SQL;
        echo "update article comments ".SITE_PREFIX.$k->post_name.'/'."\n";
        $res = $wpdb->query( $sql_update_post_id);

        if( $res ){
            echo "Update successed! -- $res \n";
        }else{
            echo "Check This article!\n";
            var_dump($res);
            echo "\n";
        }


    foreach ( $comment_ids as $k){
        $disqus_post_id = $wpdb->get_results( $wpdb->prepare($sql_get_disqus_id,(int)$k->comment_ID));
       if( $disqus_post_id[0]->meta_value   === null ) continue;
        $url =DISQUS_GET_COMMENTS. $disqus_post_id->meta_value;
        $comments =curlGet( $url);
        var_dump( $comments);
        die;

    }

=========================*/

//            $synced_disqus_id = <<<SQL
//                SELECT meta_value
//                FROM wp_commentmeta as meta
//                left join wp_comments as comments
//                on meta.comment_id = comments.comment_ID
//                where meta.meta_key = 'dsq_post_id'
//                  and comments.comment_post_id = {$this->post_id}
//            SQL;

//    $sql_update_post_id =<<<SQL
//    UPDATE wp_comments
//    SET comment_post_ID = $post_id
//    WHERE
//        comment_ID = (
//            SELECT comment_id
//            FROM wp_commentmeta
//            WHERE
//                meta_key = "dsq_post_id"
//            AND meta_value = {$comment->id}
//        );
//    SQL;


//     重新更新各篇文章的评论数目
//        echo "Recount comments of articles \n";
//        // 计算 wp_comments 相同comment_post_id  的评论的数量
//        $sql = <<<SQL
//            UPDATE wp_posts AS posts1
//            right JOIN (
//                SELECT
//                    posts.ID,
//                    count( comments.comment_ID ) AS new_count
//                FROM
//                    wp_posts AS posts
//                    LEFT JOIN wp_comments AS comments ON posts.ID = comments.comment_post_id
//                WHERE
//                    posts.post_status = 'publish'
//                    AND comments.comment_type = 'comment'
//                GROUP BY
//                    posts.ID
//                ) AS new_cout on posts1.ID = new_cout.ID
//            SET posts1.comment_count = new_cout.new_count
//        SQL;
