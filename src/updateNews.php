<?php
/*
 * Qiita API
 *
 * @docs https://qiita.com/api/v2/docs
 * @author hikarut
 */

namespace HerokuQiita;

use QiitaApi;
use QiitaApi\Exception;

require_once __DIR__ . '/Curl.php';
require_once __DIR__ . '/QiitaApiException.php';

class HerokuQiita
{
    const URL = 'https://qiita.com/api/v1/';
    const URL_V2 = 'https://qiita.com/api/v2/';
    const USER = '';
    const PASSWORD = '';
    const TOKEN = '';

    /*
     * tokenの取得
     *
     */
    public function getToken()
    {
        $entryPoint = 'auth';
        $param = array('url_name' => self::USER,
                       'password' => self::PASSWORD);
        $paramJson = json_encode($param);
        $requestUrlTokenGet = self::URL_V2 . 'auth?token=' . self::TOKEN;
        $result = QiitaApi\Curl::post($requestUrlTokenGet, $paramJson);
    }

    /*
     * 特定タグの投稿取得
     *
     * @param string $tag
     */
    public function getItems($tag)
    {
        // 特定タグの投稿取得
        // GET /api/v2/tags/:url_name/items
        $entryPoint = "tags/${tag}/items";
        // 50件取得
        $requestUrl = self::URL_V2 . $entryPoint
                      . '?per_page=50';
        $result = QiitaApi\Curl::get($requestUrl);

        return $result;
    }
    
    /*
     * 投稿の並べ替え
     *
     * @param array $result
     * @return array $data
     */
    public function sortItems($result)
    {
        // 必要なデータを配列にまとめる
        // 初期化
        $item = array('yesterday' => array(),
                      'backnumber' => array());

        // バックナンバー
        $swiftNewsOld = array();
        $i = 0;
        $j = 0;
        $yesterday = date("Ymd", strtotime("-1 day"));

        // 重複している投稿を排除するよにタイトルをまとめる
        $itemTitle = [];
        
        foreach ($result as $num => $data) {
            // 重複している記事はスキップ
            if (in_array($data['title'], $itemTitle)) {
                continue;
            }

            $updateAt = date('Ymd', strtotime($data['updated_at']));
            $createdAt = date('Ymd', strtotime($data['created_at']));
            // 前日のデータを取得
            if ($yesterday === $createdAt) {
                $item['yesterday'][$i]['title'] = $data['title'];
                $item['yesterday'][$i]['url'] = $data['url'];
                $item['yesterday'][$i]['user'] = $data['user']['id'];
                $item['yesterday'][$i]['likes_count'] = $data['likes_count'];
                $item['yesterday'][$i]['updated_at'] = date('Y/m/d H:i:s', strtotime($data['updated_at']));
                $item['yesterday'][$i]['created_at'] = date('Y/m/d H:i:s', strtotime($data['created_at']));
                // 並べ替え用
                $keyId[$num] = $data['likes_count'];
                $i++;
            } else {
                $item['backnumber'][$j]['title'] = $data['title'];
                $item['backnumber'][$j]['url'] = $data['url'];
                $item['backnumber'][$j]['user'] = $data['user']['id'];
                $item['backnumber'][$j]['likes_count'] = $data['likes_count'];
                $item['backnumber'][$j]['updated_at'] = date('Y/m/d H:i:s', strtotime($data['updated_at']));
                $item['backnumber'][$j]['created_at'] = date('Y/m/d H:i:s', strtotime($data['created_at']));
                // 並べ替え用
                $keyIdOld[$num] = $data['likes_count'];
                $j++;
            }
            $itemTitle[] = $data['title'];
        }

        // いいね数順に並び替える
        if (!empty($item['yesterday'])) array_multisort($keyId, SORT_DESC, $item['yesterday']);
        if (!empty($item['backnumber'])) array_multisort($keyIdOld, SORT_DESC, $item['backnumber']);

        return $item;
    }

    /*
     * 投稿の更新
     *
     * @param array $data
     * @param string $uuid
     * @param string or array $tag
     * @param string $title
     * @return array $response
     */
    public function updateItem($data, $uuid, $tag, $title)
    {
        // 投稿の更新
        // PUT /api/v2/items/:uuid
        // uuidには更新する記事のuuidを入れる
        $entryPoint = "items/${uuid}";
        $requestUrl = self::URL_V2 . $entryPoint;
        
        // タグの複数対応
        if (is_array($tag)) {
            foreach ($tag as $key => $value) {
                $tagStringArray[] = '{"name": "' . $value . '"}';
            }
            $tagString = implode(',', $tagStringArray);
            $tags = implode(',', $tag);
        } else {
            $tagString = '{"name": "' . $tag . '"}';
            $tags = $tag;
        }

        // 更新日
        $now = date('Y/m/d H:i:s', time());
        // タイトル用
        $titleYesterday = date("Y年m月d日", strtotime("-1 day"));
        // 記事の内容(markdown形式)
        $body = "**このページは毎日自動更新されます。前日に投稿された${tags}の記事をいいね数順に並べています。** <br>"
                . '※' . $now . '更新'
                . '<br>'
                . '<h1>' . $titleYesterday . '更新の記事</h1>';

        if (empty($data['yesterday'])) {
            $body .= "${titleYesterday}更新の記事はありません。";
        } else {
            foreach ($data['yesterday'] as $num => $news) {
                $body .= "<h6> [" . $news['title'] . "]"
                         . "(" . $news['url'] . ")"
                         ."【"
                         . $news['likes_count']
                         . "いいね"
                         . "】 "
                         . " ("
                         . $news['created_at']
                         . ")"
                         ."</h6>";
            }
        }
        
        // バックナンバーをまとめる
        $body .= "<h1>バックナンバー</h1>";
        if (empty($data['backnumber'])) {
            $body .= "バックナンバーはありません。";
        } else {
            foreach ($data['backnumber'] as $num => $news) {
                $body .= "<h6> [" . $news['title'] . "]"
                         . "(" . $news['url'] . ")"
                         ."【"
                         . $news['likes_count']
                         . "いいね"
                         . "】 "
                         . " ("
                         . $news['created_at']
                         . ")"
                         ."</h6>";
            }
        }
        
        // 特殊文字を置換
        $body = $this->replaceString($body);

        $json = '{"title": "' . $title . '",
                  "body": "' . $body . '",
                  "tags": [' . $tagString . ']' .
        '}';
        
        $result = QiitaApi\Curl::patch($requestUrl, $json);
    }

    /*
     * ユーザーごとの新着投稿の取得
     *
     * @param int $page
     * @param string $token
     */
    public function getUserNewItems($page = 1, $token = null)
    {
        $entryPoint = 'items';

        $userNewItems = array(); 
        $cnt = 0;

        for ($i = 1; $i <= $page; $i++) {
            if ($token !== null) {
                $requestUrl = self::URL_V2 . $entryPoint 
                              . '?token=' . self::TOKEN
                              . '&per_page=100'
                              . '&page=' . $i;
            } else {
                $requestUrl = self::URL_V2 . $entryPoint
                              . '?per_page=100'
                              . '&page=' . $i;
            }
            
            $newItem = QiitaApi\Curl::get($requestUrl);

            // 今日の日付
            $now = time();
            $startDate = $now;
            $endDate = $now;
            foreach ($newItem as $key => $value) {
                // 投稿の一番早い時間と遅い時間を記録
                $createdAt = strtotime($value['created_at']);
                if ($createdAt < $startDate) {
                    $startDate = $createdAt;
                }
                if ($createdAt > $endDate) {
                    $endDate = $createdAt;
                }

                // 必要な情報をユーザーごとにまとめる
                $name = $value['user']['id'];
                $title = htmlspecialchars_decode($value['title']);
                $userNewItems['user'][$name]['name'] = $name;
                $userNewItems['user'][$name]['profile_image_url'] = $value['user']['profile_image_url'];
                $userNewItems['user'][$name]['likes_count'][$title] = $value['likes_count'];
                $userNewItems['user'][$name]['items'][$title]['likes_count'] = $value['likes_count'];
                $userNewItems['user'][$name]['items'][$title]['created_at'] = date('Y/m/d H:i:s', strtotime($value['created_at']));
                $userNewItems['user'][$name]['items'][$title]['url'] = $value['url'];
            }

            // 投稿の一番早い時間と遅い時間を記録
            $userNewItems['start'] = date('Y/m/d H:i:s', $startDate);
            $userNewItems['end'] = date('Y/m/d H:i:s', $endDate);

        }

        return $userNewItems;
    }
    
    /*
     * ユーザーごとの総いいね数をまとめる
     *
     * @param array $userNewItems
     * @return array $userLike
     */
    public function getUserLike($userNewItems)
    {
        $userLike = array();
        foreach ($userNewItems as $key => $value) {
            $userLike[$key] = array_sum($value['likes_count']);
        }

        // いいね数が多い順に並び替え
        arsort($userLike);

        return $userLike;
    }

    /*
     * ユーザーランキング投稿の更新
     *
     * @param array $userNewItems
     * @param array $userLike
     * @return array $response
     */
    public function updateUserRanking($userNewItems, $userLike)
    {
        $entryPoint = 'items/fc0310a6355c3b2d3700';
        // test用
        //$entryPoint = 'items/82262f0340af8cf7b5c7';
        $requestUrl = self::URL_V2 . $entryPoint;
        
        // 更新日
        $now = date('Y/m/d H:i:s', time());
        // 集計期間
        $term = $userNewItems['start'] . ' 〜 ' . $userNewItems['end'];
        // 記事の内容(markdown形式)
        $body = '**このページは毎日自動更新されます。いいね数の多い記事を投稿したユーザーをランキング形式で表示します。** <br>'
                . '※' . $now . '更新'
                . '<br>'
                . '※集計期間：' . $term
                . '<br>';

        $i = 1;
        foreach ($userLike as $user => $stockCount) {
            // ユーザーの投稿
            $items = '';
            foreach ($userNewItems['user'][$user]['items'] as $title => $data) {
                $items .= '・[' . $title . '](' . $data['url'] .')'
                         . '【' . $data['likes_count'] . 'いいね】'
                         . '(' . $data['created_at'] . ')'
                         . '<br>';
            }

            // ランキング
            $body .= '<h1>' . $i . '位 ' 
                     . "<a href='http://qiita.com/" . $user ."'>"
                     . $user
                     . '</a>'
                     . '</h1>'
                     . '<br>'
                     . "<a href='http://qiita.com/" . $user ."'>"
                     . "<img src='" . $userNewItems['user'][$user]['profile_image_url'] . "' width='100'>"
                     . '</a>'
                     . '<br>'
                     . '**総いいね数：' . $stockCount . '**'
                     . '<br>'
                     . $items;
            $i++;
        }
        
        // 特殊文字を置換
        $body = $this->replaceString($body);

        $json = '{"title": "いいねユーザーランキング(毎日自動更新)",
                  "body": "' . $body . '",
                  "tags": [{"name": "Qiita"}, {"name": "新人プログラマ応援"}]
        }';
        
        $result = QiitaApi\Curl::patch($requestUrl, $json);

        return $result;
    }
    
    /*
     * 特殊文字の置換
     *
     * @param string $string
     * @return string $result
     */
    public function replaceString($string)
    {
        // タイトルに謎の特殊文字^Hが入ることがあったので消す
        $search = ['_', '"', ''];
        $replace = ['＿', '”', ''];
        return str_replace($search, $replace, $string);
    }
}

// タイムゾーンのセット
date_default_timezone_set('Asia/Tokyo');
$herokuQiita = new HerokuQiita();
try {
    //$herokuQiita->getToken();
    
    // swift記事まとめの投稿
    $tag = 'swift';
    $uuid = '6138e8e406da17f5b67c';
    // test用
    //$uuid = 'be65da0a5d3d3153d7e8';
    $title = 'Swift記事まとめ(毎日自動更新)';
    $item = $herokuQiita->getItems($tag);
    $sortedItem = $herokuQiita->sortItems($item);
    $herokuQiita->updateItem($sortedItem, $uuid, $tag, $title);
    
    // reactnative記事まとめの投稿
    $tag = 'reactnative';
    $uuid = '1dd6e8e3f58f89d17706';
    $title = 'React Native記事まとめ(毎日自動更新)';
    $item = $herokuQiita->getItems($tag);
    $tag2 = 'react-native';
    $item2 = $herokuQiita->getItems($tag2);
    $items = array_merge($item, $item2);
    $sortedItem = $herokuQiita->sortItems($items);
    $tagArray = [$tag, $tag2];
    $herokuQiita->updateItem($sortedItem, $uuid, $tagArray, $title);

    //  ユーザーランキングの投稿
    $userNewItems = $herokuQiita->getUserNewItems(10);
    $userLike = $herokuQiita->getUserLike($userNewItems['user']);
    $userLike10 = array_slice($userLike, 0, 10);
    $herokuQiita->updateUserRanking($userNewItems, $userLike10);

} catch (Exception\QiitaApiException $e) {
    echo $e->getMessage() . "\n";
}

