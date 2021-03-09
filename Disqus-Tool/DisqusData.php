<?php
    /**
     *@author Shawn.yao
     *@version version 1.0
     *
     */

    class DisqusData
    {

        const SYNC_SUCCESS = 1;
        const SYNC_FAILED = 2;
        const UNSYNCED = 3;

        /**
         * @var int $post_id 评论所在文章id
         */
        private $post_id;
        /**
         * @var string $disqus_thread_id disqus对应的threadid
         */
        private $disqus_thread_id;
        /**
         * @var array $comments 文章下所有的评论信息
         */
        private $comments;
        /**
         * @var int $comments_count 该篇文章下的评论总数
         */
        public $comments_count;
        /**
         * @var object $wpdb wp数据库连接
         */
        private $wpdb;
        /**
         * @var array $sync_list 数据库中已经同步的数据
         */
        public $sync_list = [];

        public function __construct( $post_id, $thread_id, $comments, $wpdb, $sync_list = [])
        {
            $this->post_id = $post_id;
            $this->disqus_thread_id = $thread_id;
            $this->comments = $comments;
            $this->comments_count = count( $comments );
            $this->wpdb = $wpdb;
            $this->sync_list = $sync_list;
        }

        public function getSyncList()
        {
            # 直接获取全部的 disqus_post_id 然后进行对比
            $synced_disqus_id = <<<SQL
                SELECT meta_value
                FROM wp_commentmeta 
                where meta_key = 'dsq_post_id'
            SQL;
            $this->sync_list = $this->wpdb->get_col($synced_disqus_id);
        }

        /**
         * 给出本地与disqus上同步的状态 ：正确同步，错误同步，未同步
         * @param string $disqus_post_id
         * @return int
         */
        public function syncStatus(string $disqus_post_id)
        {
            (!empty( $this->sync_list) )?: $this->getSyncList();
            if (in_array($disqus_post_id, $this->sync_list)) {
                $get_comment_post_id = <<<SQL
                    SELECT comment_post_ID 
                    FROM wp_comments 
                    where comment_ID = (
                        SELECT comment_id 
                        FROM wp_commentmeta 
                        WHERE meta_key = 'dsq_post_id' 
                          AND meta_value = "{$disqus_post_id}"
                    )
                SQL;
                $synced_id = $this->wpdb->get_var($get_comment_post_id);
                if ($synced_id == $this->post_id) return self::SYNC_SUCCESS;
                return self::SYNC_FAILED;
            } else {
                return self::UNSYNCED;
            }
        }

        /**
         * @param int $comment_index
         * @return mixed
         */
        public function solveComment(int $comment_index)
        {
            switch ($this->syncStatus($this->comments[$comment_index]->id)) {
                case self::SYNC_FAILED:
                    echo "SYNC_FAILED COMMENT --  \n";
                    $this->updateComment($this->comments[$comment_index]);
                    break;
                case self::UNSYNCED:
                    echo "UNSYNCED COMMENT --  \n";
                    $this->insertComment($this->comments[$comment_index]);
                    break;
            }

            return $this->comments[$comment_index]->id;
        }

        /** 插入未同步到数据库的评论信息
         * @param object $comment_info
         */
        private function insertComment(object $comment_info)
        {
            $parent_comment_id = 0;
            $standerd_time = date('Y-m-d H:i:s', strtotime($comment_info->createdAt));

            if ($comment_info->parent != null) {
                $sql_find_parent_id = <<<SQL
                SELECT comments.comment_ID 
                FROM wp_comments as comments 
                    LEFT JOIN wp_commentmeta as meta 
                        on comments.comment_ID = meta.comment_id 
                WHERE meta_key = 'dsq_post_id' 
                AND meta_value = "{$comment_info->parent}"
                SQL;
                $parent_comment_id = $this->wpdb->get_var($sql_find_parent_id);
            }
            $sql_insert_2_comments = <<<SQL
            INSERT INTO wp_comments (
                comment_post_ID,
                comment_author,
                comment_author_email,
                comment_author_url,
                comment_author_IP,
                comment_date,
                comment_date_gmt,
                comment_content,
                comment_karma,
                comment_approved,
                comment_agent,
                comment_type,
                comment_parent,
                user_id 
            )
            VALUES
                (
                    "{$this->post_id}",
                    "{$comment_info->author->name}",
                    '***super@manualSync.com',
                    "{$comment_info->author->url}",
                    '***.***.***.007',
                    "{$standerd_time }",
                    "{$standerd_time}",
                    "{$comment_info->raw_message}",
                    0,
                    0,
                    'Disqus Sync Host',
                    'comment',
                    {$parent_comment_id},
                    0
                )
            SQL;
            $this->wpdb->query($sql_insert_2_comments);
            $comment_id = $this->wpdb->insert_id;
            $sql_insert_2_meta = <<<SQL
            INSERT INTO wp_commentmeta (
                comment_id,
                meta_key,
                meta_value
            )VALUE(
                {$comment_id},
                'dsq_post_id',
                {$comment_info->id}
            )
            SQL;
            $this->wpdb->query($sql_insert_2_meta);
            echo "INSERT: post_id -- $this->post_id , comment_parent: -- $comment_info->parent, parent_comment_id: -- $parent_comment_id, dsq_post_id: -- $comment_info->id, message -- $comment_info->raw_message \n";

        }

        /** 插入未同步到数据库的评论信息  最后统一进行删除 */
        /** @param array $solved_list
         * 最终处理完之后汇总成的 disqus_post_id 数组
         *
         */
        public  function deleteComment(array $solved_list)
        {
            for($i = 0; $i < count($this->sync_list); $i++){
                if( ! in_array( $this->sync_list[$i], $solved_list)){

                    $sql_delete_meta =<<<SQL
                    DELETE FROM wp_commentmeta 
                    WHERE meta_key = 'dsq_post_id' 
                      AND meta_value = "{$this->sync_list[$i]}"
                    SQL;

                    $sql_delete_comments =<<<SQL
                    DELETE FROM wp_comments 
                    WHERE comment_ID = (
                        SELECT comment_id 
                        FROM wp_commentmeta
                        WHERE meta_key = 'dsq_post_id'
                        AND   meta_value = "{$this->sync_list[$i]}"
                    )
                    SQL;

                    $this->wpdb->query( $sql_delete_comments);
                    $this->wpdb->query( $sql_delete_meta );
                    echo "DELETE: post_id -- $this->post_id , dsq_post_id: -- {$this->sync_list[$i]}  \n";
                }
            }
        }

        /**
         * @param object $comment_info
         */
        private function updateComment(object $comment_info)
        {
            $sql_update_post_id = <<<SQL
                UPDATE wp_comments 
                SET comment_post_ID = {$this->post_id}
                WHERE
                    comment_ID = (
                        SELECT comment_id 
                        FROM wp_commentmeta 
                        WHERE
                            meta_key = "dsq_post_id" 
                        AND meta_value = "{$comment_info->id}"
                    );
            SQL;
            $this->wpdb->query($sql_update_post_id);
            echo "UPDATE: post_id -- $this->post_id , dsq_post_id: -- $comment_info->id \n";
        }

        public function reCountComment()
        {
            $sql_update_comment_count = <<<SQL
                UPDATE wp_posts AS posts1
                right JOIN (
                    SELECT
                        posts.ID,
                        count( comments.comment_ID ) AS new_count
                    FROM
                        wp_posts AS posts
                        LEFT JOIN wp_comments AS comments ON posts.ID = comments.comment_post_id
                    WHERE
                        posts.post_status = 'publish'
                        AND comments.comment_type = 'comment'
                    GROUP BY
                        posts.ID
                    ) AS new_cout on posts1.ID = new_cout.ID
                SET posts1.comment_count = new_cout.new_count
            SQL;

            $this->wpdb->query($sql_update_comment_count);
            echo "Recount Complete! \n ";
        }

    }