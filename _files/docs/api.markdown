# API Documents

## Authorization
The API implements the OAuth 2.0 Authorization Framework ([RFC 6749](http://tools.ietf.org/html/rfc6749)).

### Supported scopes
 * `read`
 * `post`
 * `usercp`
 * `conversate`
 * `admincp`

### Supported grant types
 * Authorization code
 * Implicit
 * User credentials (username / password)
 * Client credentials
 * Refresh token
 * JWT bearer

### One Time Token
Any client can generate one time token (OTT) using existing token. The format for OTT is as follow:

    user_id,timestamp,once,client_id

With `user_id` is the ID of authenticated user; `timestamp` is the unix timestamp for OTT expire time, the OTT will work as long as indicated by `timestamp` or by token expire date, whatever comes first; `client_id` is the client ID; `once` is md5 of a concentration of `user_id`, `timestamp`, a valid existing token and the client secret. Example code to generate an OTT:

    <?php

    $timestamp = time() + $ttl;
    $once = md5($userId . $timestamp . $accessToken . $clientSecret);

    $ott = sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $clientId);

### Configuration
 * TTL of access token: 1 hour
 * TTL of authorization code: 30 seconds
 * TTL of refresh token: 2 weeks
 * Authorization URI: `/oauth/authorize`
 * Access token exchange URI: `/oauth/token`
 * Token param name: `oauth_token`
 * Token bearer header name: `Bearer`

Please note that the TTL can be reconfigured in XenForo AdminCP options page.

### Social Logins
Since oauth2-2015030602, social logins are accepted as a way to authorize access to an user account. List of supported services: Facebook, Twitter and Google. The common flow is:

 1. Third party client authorizes user via social service (e.g. awesomewebsite.com use Facebook Application A to authorize user).
 2. Third party client obtains access token to social service (e.g. awesomewebsite.com has Facebook access token T).
 3. Third party client submits access token to API endpoint to request access token to XenForo (e.g. awesomewebsite.com sends Facebook access token T to xenforo.com).
 4. XenForo verifies that access token is valid and third party client does have access to a XenForo account (e.g. xenforo.com contacts Facebook server to verify access token T then cross-matches to a XenForo user account U).
 5. XenForo generates API access token for its account (e.g. xenforo.com generates access token T2 which can be used in future API requests).

#### Responses
If an API access token can be generated, the response is similar to a regular POST `/oauth/token` with token and refresh_token amongst other things. Previously user-granted scopes and auto-authorized scopes will be attached to the token.

Otherwise, the response is as follow:

    {
        status: 'ok',
        message: (string),
        user_data: {
            username: (string),
            user_email: (string),
            user_dob_year: (int),
            user_dob_month: (int),
            user_dob_day: (int),
            associatable: {
                (user_id): {
                    user_id: (int),
                    username: (string), # since oauth2-2016030902
                    user_email: (string)
                },
                ...
            },
            extra_data: (string),
            extra_timestamp: (unix timestamp in seconds)
        }
    }

Information in `user_data` is optional and can be used to pre-fill register forms (POST `/users`). `extra_data` and `extra_timestamp` contain encrypted information that needs to be submitted upon registration to associate social account and the newly created user account. In case existing accounts are found with the social account information, `associatable` contains id and email of those account for manual association via POST `/oauth/token/associate`.

#### POST `/oauth/token/facebook`
Request API access token using Facebook access token. Please note that because Facebook uses app-scoped user_id, it is not possible to recognize user across different Facebook Applications.

Parameters:

 * `client_id` (__required__)
 * `client_secret` (__required__)
 * `facebook_token` (__required__)

Required scopes:

 * N/A

#### POST `/oauth/token/twitter`
Request API access token using Twitter access token. The `twitter_uri` and `twitter_auth` parameters are similar to `X-Auth-Service-Provider` and `X-Verify-Credentials-Authorization` as specified in Twitter's [OAuth Echo](https://dev.twitter.com/oauth/echo) specification.

Parameters:

 * `client_id` (__required__)
 * `client_secret` (__required__)
 * `twitter_uri` (__required__): the full `/account/verify_credentials.json` uri that has been used to calculate OAuth signature. For security reason, the uri must use HTTPS protocol and the hostname must be either "twitter.com" or "api.twitter.com".
 * `twitter_auth` (__required__): the complete authentication header that starts with "OAuth". Consult [Twitter document](https://dev.twitter.com/oauth/overview/creating-signatures) for more information.

Required scopes:

 * N/A

#### POST `/oauth/token/google`
Request API access token using Google access token.

Parameters:

 * `client_id` (__required__)
 * `client_secret` (__required__)
 * `google_token` (__required__)

Required scopes:

 * N/A

#### POST `/oauth/token/admin`
Request API access token for another user. This requires `admincp` scope and the current user must have sufficient system permissions. Since oauth2-2015030902.

Parameters:

 * `user_id` (__required__): id of the user that needs access token.

Required scopes:

 * `admincp`

#### POST `/oauth/token/associate`
Request API access token and associate social account with an existing user account. Since oauth2-2016030901.

Parameters:

 * `client_id` (__required__)
 * `user_id` (__required__)
 * `password` (__required__): can be used with `password_algo` for better security. See [Encryption](#encryption) section for more information.
 * `extra_data` (__required__)
 * `extra_timestamp` (__required__)

Required scopes:

 * N/A

### Two-Factor Authentication
Since oauth2-2015121501, user credentials grant flow will require additional verfications for users who have tfa configured (available since XenForo 1.5.0). If username and password can be verified, client will receive a response with HTTP Status Code 202 (Accepted) to continue verifying tfa. The response will also include the header `X-Api-Tfa-Providers` which is a comma separated list of available tfa providers. Client needs to resend POST `/oauth/token` with additional parameters:

 * `tfa_provider`: id of the chosen provider.
 * `tfa_trigger`: 1 if the provider requires trigger to work. Currently, only `email` provider needs this.
 * `code`: the verification code.
 * Note: other parameters may be required by third party tfa providers.

Normal flow for `totp` and `backup` verification:

 1. Client sends standard POST `/oauth/token`
 2. Server responses with HTTP 202 Accepted, `X-Api-Tfa-Providers` = `totp, backup`
 3. Client sends POST `/oauth/token` with all standard parameters plus `tfa_provider` = `totp`/`backup` and `code` = `123456`
 4. Server responses with API access tokens

Flow for `email` tfa verification:

 1. Client sends standard POST `/oauth/token`
 2. Server responses with HTTP 202 Accepted, `X-Api-Tfa-Providers` = `email`
 3. Client sends POST `/oauth/token` with all standard parameters plus `tfa_provider` = `email` and `tfa_trigger` = `1`
 4. User waits for his/her email from XenForo
 5. Client sends POST `/oauth/token` with all standard parameters plus `tfa_provider` = `email` and `code` = `123456`
 6. Server responses with API access tokens

## Discoverability
System information and availability can be determined by sending a GET request to `/` (index route). A list of resources will be returned. If the request is authenticated, the revisions of API system and installed modules will also made available for further inspection.

## Common Parameters

### i18n
All API requests accept `locale` parameter to switch user facing messages to specified language. The value must be a valid language code (ISO 639-1) with optional inclusion of a valid country code (ISO 3166-1 alpha 2) separated by hyphen ("-"). If no complete match can be found, a language with the same language code (even with different country code) will be used. In the worst case that there are no installed languages of requested language code, the default language will be used. Since api-2015100401.

### Fields filtering
For API method with resource data like a forum or a thread, the data can be filtered to get interested fields only. The general format is "key.sub_key.deep_key" if you want to include/exclude a specific field. The including rules take precedence over excluding ones.

 * `fields_include`: coma-separated list of fields of a resource. For additional fields, it is possible to use wildcard (`*`) to include all default fields before specifying addtional ones.
 * `fields_exclude`: coma-separated list of fields of a resource to exclude in the response. Since r2016062001, it is possible to use wildcard as a prefix (e.g. "\*.key") to exclude the field everywhere.

### Resource ordering
For API method with list of resources, the resources can be ordered differently with the parameter `order`. List of supported orders will be specified for each method. The default order will always be `natural`. Most of the time, the natural order is the order of which each resource is added to the system (resource id for example).

### Encryption
For sensitive information like password, encryption can be used to increase data security. For all encryption with key support, the `client_secret` will be used as the key. List of supported encryptions:

 * `aes128`: AES 128 bit encryption (mode: ECB, padding: PKCS#7). Because of algorithm limitation, the binary md5 hash of key will be used instead of the key itself.

## Categories

### GET `/categories`
List of all categories in the system.

    {
        categories: [
            (category),
            ...
        ],
        categories_total: (int)
    }

Parameters:

 * `parent_category_id` (_optional_): id of parent category. If exists, filter categories that are direct children of that category.
 * `parent_forum_id` (_optional_): id of parent forum. If exists, filter categories that are direct children of that forum.
 * `order` (_optional_): ordering of categories. Support `natural`, `list`.

Required scopes:

 * `read`

### GET `/categories/:categoryId`
Detail information of a category.

    {
        category: {
            category_id: (int),
            category_title: (string),
            category_description: (string),
            links: {
                permalink: (uri),
                detail: (uri),
                sub-categories: (uri),
                sub-forums: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

## Forums

### GET `/forums`
List of all forums in the system.

    {
        forums: [
            (forum),
            ...
        ],
        forums_total: (int)
    }

Parameters:

 * `parent_category_id` (_optional_): id of parent category. If exists, filter forums that are direct children of that category.
 * `parent_forum_id` (_optional_): id of parent forum. If exists, filter forums that are direct children of that forum.
 * `order` (_optional_): ordering of forums. Support `natural`, `list`.

Required scopes:

 * `read`

### GET `/forums/:forumId`
Detail information of a category.

    {
        forum: {
            forum_id: (int),
            forum_title: (string),
            forum_description: (string),
            forum_thread_count: (int),
            forum_post_count: (int),
            forum_is_follow: (boolean), # since forum-2014053001
            forum_prefixes: { # since forum-2016031001
                {
                    group_title: (string),
                    group_prefixes: [
                        {
                            prefix_id: (int),
                            prefix_title: (string)
                        },
                        ...
                    ]
                },
                ...
            },
            thread_default_prefix_id: (int), # since forum-2016031001
            thread_prefix_is_required: (boolean), # since forum-2016031001
            links: {
                permalink: (uri),
                detail: (uri),
                followers: (uri), # since forum-2014053001
                sub-categories: (uri),
                sub-forums: (uri),
                threads: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                follow: (boolean), # since forum-2014053001
                create_thread: (boolean),
                upload_attachment: (boolean) # since forum-2014081202
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### GET `/forums/:forumId/followers`
List of a forum's followers. For privacy reason, only the current user will be included in the list (if the user follows the specified forum). Since forum-2014053001.

    {
        users: [
            {
                user_id: (int),
                username: (string),
                follow: {
                    post: (boolean),
                    alert: (boolean),
                    email: (alert)
                }
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### POST `/forums/:forumId/followers`
Follow a forum.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `post` (_optional_): whether to receive notification for post (value = 1) or just thread (value = 0). Default value: 0.
 * `alert` (_optional_): whether to receive notification as alert. Default value: 1.
 * `email` (_optional_): whether to receive notification as email. Default value: 0.

Required scopes:

 * `post`

### DELETE `/forums/:forumId/followers`
Un-follow a forum.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/forums/followed`
List of followed forums by current user. Since forum-2014053001.

    {
        forums: [
            (forum) + {
                follow: {
                    post: (boolean),
                    alert: (boolean),
                    email: (alert)
                }
            },
            ...
        ]
    }

Parameters:

 * `total` (_optional_): if included in the request, only the forum count is returned as `forums_total`. Since forum-2015072305.

Required scopes:

 * `read`

 ## Pages

 ### GET `/pages`
 List of all pages in the system. Since forum-2015072302.

     {
         pages: [
             (page),
             ...
         ],
         pages_total: (int)
     }

 Parameters:

  * `parent_page_id` (_optional_): id of parent page. If exists, filter pages that are direct children of that page.
  * `order` (_optional_): ordering of pages. Support `natural`, `list`.

 Required scopes:

  * `read`

 ### GET `/pages/:pageId`
 Detail information of a page. Since forum-2015072302.

     {
         page: {
             page_id: (int),
             page_title: (string),
             page_description: (string),
             page_view_count: (int),
             page_html: (string),
             links: {
                 permalink: (uri),
                 detail: (uri),
                 sub-pages: (uri)
             },
             permissions: {
                 view: (boolean),
                 edit: (boolean),
                 delete: (boolean)
             }
         }
     }

 Parameters:

  * N/A

 Required scopes:

  * `read`

## Navigation

### GET `/navigation`
List of navigation elements within the system. Since forum-2015030601.

    {
        elements: [
            (category) + {
                navigation_type: "category",
                navigation_id: (int),
                has_sub_elements: (boolean)
            },
            (forum) + {
                navigation_type: "forum",
                navigation_id: (int),
                has_sub_elements: (boolean)
            },
            {
                link_id: (int),
                link_title: (string),
                link_description: (string),
                links {
                    target: (uri),
                    sub-elements: (uri),
                },
                permissions: {
                    view: (boolean),
                    edit: (boolean),
                    delete: (boolean),
                },
                navigation_type: "linkforum",
                navigation_id: (int),
                has_sub_elements: (boolean)
            },
            ...
        ],
        elements_count: (int)
    }

Parameters:

 * `parent` (_optional_): id of parent element. If exists, filter elements that are direct children of that element.

Required scopes:

 * `read`

## Threads

### GET `/threads`
List of threads in a forum (with pagination).

    {
        threads: [
            (thread),
            ...
        ],
        threads_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        },
        forum: (forum), # since forum-2014103002
        creator_user: (user), # since forum-2017071201
        thread_prefixes: [ # since forum-2017071101
            {
                group_title: (string),
                group_prefixes: [
                    {
                        prefix_id: (int),
                        prefix_title: (string)
                    },
                    ...
                ]
            },
            ...
        ],
        thread_tag: (tag) # since forum-2017082201
    }

Note: A valid token is required if `forum_id` is not provided. Since forum-2017111102.

Parameters:

 * `forum_id` (_optional_): ids of the containing forums (separated by comma). Support for multiple ids were added in forum-2014011801. Prior to forum-2017010302, this parameter was required.
 * `thread_ids` (_optional_): ids of needed threads (separated by comma). If this parameter is set, all other filtering parameters will be ignored. Since forum-2015032401.
 * `creator_user_id` (_optional_): filter to get only threads created by the specified user. Since forum-2017071201.
 * `sticky` (_optional_): filter to get only sticky (`sticky`=1) or non-sticky (`sticky`=0) threads. By default, all threads will be included and sticky ones will be at the top of the result on the first page. In mixed mode, sticky threads are not counted towards `threads_total` and does not affect pagination.
 * `thread_prefix_id` (_optional_): filter to get only threads with the specified prefix. Since forum-2017071101.
 * `thread_tag_id` (_optional_): filter to get only threads with the specified tag. Since forum-2017082201.
 * `page` (_optional_): page number of threads.
 * `limit` (_optional_): number of threads in a page. Default value depends on the system configuration.
 * `order` (_optional_): ordering of threads. Support `natural`, `thread_create_date`, `thread_create_date_reverse`, `thread_update_date`, `thread_update_date_reverse`, `thread_view_count` (since forum-2014011801), `thread_view_count_reverse` (since forum-2014011801), `thread_post_count` (since forum-2014011801), `thread_post_count_reverse` (since forum-2014011801).

Required scopes:

 * `read`

### POST `/threads`
Create a new thread.

    {
        thread: (thread)
    }

Parameters:

 * `forum_id` (__required__): id of the target forum.
 * `thread_title` (__required__): title of the new thread.
 * `post_body` (__required__): content of the new thread.
 * `thread_prefix_id` (_optional_): id of a prefix for the new thread. Since forum-2016031001.
 * `thread_tags` (_optional_): thread tags for the new thread. Since forum-2015091002.
 * `fields` (_optional_): thread fields for the new thread. Since forum-2018073000

Required scopes:

 * `post`

### POST `/threads/attachments`
Upload an attachment for a thread.

    {
        attachment: (post > attachment)
    }

Parameters:

* `file` (__required__): binary data of the attachment.
* `forum_id` (__required__): id of the container forum of the target thread.
* `attachment_hash` (_optional_, since forum-2014052202): a unique hash value.

Required scopes:

* `post`

### DELETE `/threads/attachments`
Delete an attachment for a thread.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `forum_id` (__required__): id of the container forum of the target thread.
 * `attachment_id` (__required__): id of the attachment.
 * `attachment_hash` (_optional_, since forum-2014052202): the hash that was used when the attachment was uploaded (use only if the attachment hasn't been associated with a thread).

Required scopes:

 * `post`

### GET `/threads/:threadId`
Detail information of a thread.

    {
        thread: {
            thread_id: (int),
            forum_id: (int),
            thread_title: (string),
            thread_view_count: (int),
            thread_post_count: (int),
            creator_user_id: (int),
            creator_username: (string),
            user_is_ignored: (boolean), # since forum-2015072304
            thread_create_date: (unix timestamp in seconds),
            thread_update_date: (unix timestamp in seconds),
            thread_is_published: (boolean),
            thread_is_deleted: (boolean),
            thread_is_sticky: (boolean),
            thread_is_followed: (boolean), # since forum-2014052903
            first_post: (post),
            last_post: (post), # must be in fields_include to work, since forum-2015121801
            latest_posts: [ # must be in fields_include to work, since forum-2016061801
                (post),
                ...
            ],
            thread_prefixes: [ # since forum-2016031001
                {
                    prefix_id: (int),
                    prefix_title: (string)
                },
                ...
            ],
            poll: { # since forum-2015100601
                poll_id: (int),
                poll_question: (string),
                poll_vote_count: (int),
                poll_is_open: (boolean),
                poll_is_voted: (boolean),
                poll_max_votes: (int),
                responses: [
                    {
                        response_id: (int),
                        response_answer: (string),
                        response_is_voted: (boolean)
                    },
                    ...
                ],
                permissions: {
                    vote: (boolean),
                    result: (boolean)
                },
                links: {
                    votes: (uri),
                    results: (uri)
                }
            },
            thread_tags { # since forum-2015091001
                (tag id): (tag text),
                ...
            },
            links: {
                permalink: (uri),
                detail: (uri),
                forum: (uri),
                posts: (uri),
                first_poster: (uri),
                first_poster_avatar: (uri), # since forum-2016091201
                first_post: (uri),
                last_poster: (uri),
                last_post: (uri)
            },
            permissions: {
                view: (boolean),
                delete: (boolean),
                follow: (boolean), # since forum-2014052903
                post: (boolean),
                upload_attachment: (boolean), # since forum-2014081203
                edit: (boolean),
                edit_title: (boolean), # since forum-2015100701
                edit_tags: (boolean), # since forum-2015091102
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### PUT `/threads/:threadId`
Alias for PUT `/posts/:postId`. Since forum-2015091102.

### DELETE `/threads/:threadId`
Delete a thread.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `reason` (_optional_, since forum-2016042501): reason of the thread removal.

Required scopes:

 * `post`

### GET `/threads/:threadId/followers`
List of a thread's followers. For privacy reason, only the current user will be included in the list (if the user follows the specified thread). The privacy change was put in place since forum-2014053001, earlier versions return all followers of the thread.

    {
        users: [
            {
                user_id: (int),
                username: (string),
                follow: {
                    alert: (boolean:true),
                    email: (boolean),
                }
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### POST `/threads/:threadId/followers`
Follow a thread.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `email` (_optional_): whether to receive notification as email. Default value: 0. Since forum-2014053002.

Required scopes:

 * `post`

### DELETE `/threads/:threadId/followers`
Un-follow a thread.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/threads/followed`
List of followed threads by current user. Since forum-2014053002.

    {
        threads: [
            (thread) + {
                follow: {
                    alert: (boolean:true),
                    email: (boolean)
                }
            },
            ...
        ]
    }

Parameters:

 * `total` (_optional_): if included in the request, only the thread count is returned as `threads_total`. Since forum-2015072305.

Required scopes:

 * `read`

### POST `/threads/:threadId/poll/votes`
Vote on a thread poll. Since forum-2015100601.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `response_id` (__required__): the id of the response to vote for.
 * `response_ids` (_optional_): an array of ids of responses (if the poll allows multiple choices).

Required scopes:

 * `post`

### GET `/threads/:threadId/poll/results`
List of poll responses with their results. Since forum-2015100601.

    {
        results: [
            {
                response_id: (int),
                response_answer: (string),
                response_is_voted: (boolean),
                response_vote_count: (int),
                voters: [
                    {
                        user_id: (int),
                        username: (string)
                    },
                    ...
                ]
            }
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### GET `/threads/new`
List of unread threads (must be logged in).

    {
        threads: [
            {
                thread_id: (int)
            },
            ...
        ],
        data: [ # since forum-2015121802
            (thread),
            ...
        ]
    }

Parameters:

 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.
 * `data_limit` (_optional_): number of thread data to be returned. By default, no data is returned. Since forum-2015121802.

Required scopes:

 * `read`

### GET `/threads/recent`
List of recent threads.

    {
        threads: [
            {
                thread_id: (int)
            },
            ...
        ],
        data: [ # since forum-2015121802
            (thread),
            ...
        ]
    }

Parameters:

 * `days` (_optional_): maximum number of days to search for threads.
 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.
 * `data_limit` (_optional_): number of thread data to be returned. By default, no data is returned. Since forum-2015121802.

Required scopes:

 * `read`

## Posts

### GET `/posts`
List of posts in a thread (with pagination).

    {
        posts: [
            (post),
            ...
        ],
        posts_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        },
        thread: (thread), # since forum-2014103001
        subscription_callback: (uri) # since subscription-2014081002
    }

Note: A valid token is required if `thread_id` is not provided. Since forum-2017111102.

Parameters:

 * `thread_id` (_optional_): id of the containing thread. Prior to forum-2017010301, this parameter was required.
 * `page_of_post_id` (_optional_): id of a post, posts that are in the same page with the specified post will be returned. `thread_id` may be skipped. Since forum-2014092401.
 * `post_ids` (_optional_): ids of needed posts (separated by comma). If this parameter is set, all other filtering parameters will be ignored. Since forum-2015032403.
 * `page` (_optional_): page number of posts.
 * `limit` (_optional_): number of posts in a page. Default value depends on the system configuration.
 * `order` (_optional_): ordering of posts. Support `natural`, `natural_reverse` (since forum-2013122401), `post_create_date` (since forum-2017010301), `post_create_date_reverse` (since forum-2017010301).

Required scopes:

 * `read`

### POST `/posts`
Create a new post.

    {
        post: (post)
    }

Parameters:

 * `thread_id` (__required__): id of the target thread.
 * `quote_post_id` (_optional_): id of the quote post. It's possible to skip `thread_id` if this parameter is provided. An extra check is performed if both parameters exist and does not match. Since forum-2017110801.
 * `post_body` (__required__): content of the new post.

Required scopes:

 * `post`

### POST `/posts/attachments`
Upload an attachment for a post. The attachment will be associated after the post is saved.

    {
        attachment: (post > attachment)
    }

Parameters:

 * `file` (__required__): binary data of the attachment.
 * `thread_id` (_optional_): id of the container thread of the target post.
 * `post_id` (_optional_): id of the target post.
 * `attachment_hash` (_optional_, since forum-2014052202): a unique hash.

Parameters Note: either `thread_id` or `post_id` parameter must has a valid id. Simply speaking, `thread_id` must be used with POST `/posts` (creating a new post) while `post_id` must be used with PUT `/posts/:postId` (editing a post).

Required scopes:

* `post`

### GET `/posts/:postId`
Detail information of a post.

    {
        post: {
            post_id: (int),
            thread_id: (int),
            poster_user_id: (int),
            poster_username: (string),
            user_is_ignored: (boolean), # since forum-2015072304
            post_create_date: (unix timestamp in seconds),
            post_update_date: (unix timestamp in seconds), #since forum-2015030701
            post_body: (string),
            post_body_html: (string),
            post_body_plain_text: (string),
            signature: (string), # since forum-2014082801
            signature_html: (string), # since forum-2014082801
            signature_plain_text: (string), # since forum-2014082801
            post_like_count: (int),
            post_attachment_count: (int),
            post_is_published: (boolean),
            post_is_deleted: (boolean),
            post_is_first_post: (boolean), # since forum-2013122402
            post_is_liked: (boolean),
            post_origin: (string), # since forum-2017081401
            attachments: [
                {
                    attachment_id: (int),
                    post_id: (int),
                    attachment_download_count: (int),
                    filename: (string), # since 2014052201
                    attachment_is_inserted: (boolean), # since forum-2014091001
                    links: {
                        permalink: (uri),
                        data: (uri),
                        thumbnail: (uri)
                    },
                    permissions: {
                        view: (boolean),
                        delete: (boolean) # since forum-2014081201
                    }
                },
                ...
            ],
            links: {
                permalink: (uri),
                detail: (uri),
                thread: (uri),
                poster: (uri),
                likes: (uri),
                report: (uri), # since forum-2014103003
                attachments: (uri),
                poster_avatar: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                reply: (boolean), #since forum-2014052901
                like: (boolean),
                report: (boolean), # since forum-2014103003
                upload_attachment: (boolean) # since forum-2014081204
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### PUT `/posts/:postId`
Edit a post.

    {
        post: (post)
    }

Parameters:

 * `post_body` (__required__): new content of the post.
 * `thread_title` (_optional_, since forum-2014052203): new title of the thread (only used if the post is the first post in the thread and the authenticated user can edit thread).
 * `thread_prefix_id` (_optional_, since forum-2016031001): new id of the thread's prefix (only used if the post is the first post in the thread and the authenticated user can edit thread).
 * `thread_tags` (_optional_, since forum-2015091101): new tags of the thread (only used if the post is the first post in the thread and the authenticated user can edit thread tags).
* `fields` (_optional_, since forum-2018082101): new fields of the thread (only used if the post is the first post in the thread)

Required scopes:

 * `post`

### DELETE `/posts/:postId`
Delete a post.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `reason` (_optional_, since forum-2016042501): reason of the post removal.

Required scopes:

 * `post`

### GET `/posts/:postId/attachments`
List of attachments of a post.

    {
        attachments: [
            (post > attachment),
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### GET `/posts/:postId/attachments/:attachmentId`
Binary data of a post's attachment.

Parameters:

 * `max_width` (_optional_): maximum width required (applicable for image attachment only).
 * `max_height` (_optional_): maximum height required (applicable for image attachment only).
 * `keep_ratio` (_optional_): whether to keep original ratio during resizing (applicable for image attachment only).

Required scopes:

 * `read`

### DELETE `/posts/:postId/attachments/:attachmentId`
Delete a post's attachment.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `thread_id` (_optional_): id of the container thread of the target post (use only if the attachment hasn't been associated with a post).
 * `attachment_hash` (_optional_, since forum-2014052202): the hash that was used when the attachment was uploaded (use only if the attachment hasn't been associated with a post).

Required scopes:

 * `post`

### GET `/posts/:postId/likes`
List of users who liked a post.

    {
        users: [
            {
                user_id: (int),
                username: (string)
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### POST `/posts/:postId/likes`
Like a post.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### DELETE `/posts/:postId/likes`
Unlike a post.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### POST `/posts/:postId/report`
Report a post.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `message` (__required__): reason of the report.

Required scopes:

 * `post`

## Content Tagging

### GET `/tags`
List of popular tags (no pagination). Since forum-2015091002.

    {
        tags: {
            (tag id): (tag text),
            ...
        }
    }

Required scopes:

 * `read`

### GET `/tags/list`
List of tags. Since forum-2017111101.

    {
        tags: {
            (tag id): (tag text),
            ...
        },
        tags_total: (int),
        links {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Note: A valid token is required.

Required scopes:

 * `read`

## Get `/tags/:tagId`
List of tagged contents. Since forum-2017050201.

    {
        tag: {
          tag_id: (int),
          tag_text: (string),
          tag_use_count: (int), # since forum-2017091101
          links: {
            permalink: (uri),
            detail: (uri) # since forum-2017082201
          }
        },
        tagged: [
            (content),
            ...
        ],
        tagged_total: (int),
        links {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `page` (_optional_): page number of tagged contents.
 * `limit` (_optional_): number of tagged contents in a page. Default value depends on the system configuration.

Required scopes:

 * `read`

### GET `/tags/find`
Filtered list of tags. Since forum-2015091002.

    {
        tags: [
            (tag text),
            ...
        ]
    }

Parameters:

 * `tag` (__required__): tag to filter. Tags start with the query will be returned.

Required scopes:

 * `post`

## Users

### GET `/users`
List of users (with pagination).

    {
        users: [
            (user),
            (user),
            ...
        ],
        users_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `page` (_optional_): page number of users.
 * `limit` (_optional_): number of users in a page. Default value depends on the system configuration.

Required scopes:

 * `read`

### POST `/users`
Create a new user.

    {
        user: (user),
        token: (token)
    }

Parameters:

 * `user_email` (__required__): email of the new user.
 * `username` (__required__): username of the new user.
 * `password` (__required__): password of the new user.
 * `password_algo` (_optional_): algorithm used to encrypt the `password` parameter. See [Encryption](#encryption) section for more information.
 * `user_dob_day` (_optional_): date of birth (day) of the new user.
 * `user_dob_month` (_optional_): date of birth (month) of the new user.
 * `user_dob_year` (_optional_): date of birth (year) of the new user.
 * `client_id` (_optional_): client ID of the Client. This parameter is required if the request is unauthorized (no `oauth_token`).
 * `extra_data`, `extra_timestamp` (_optional_): encrypted information generated previously for internal tasks.

Required scopes:

 * `post`

### GET `/users/find`
Filtered list of users by username or email. Since forum-2015030901.

    {
        users: [
            (user),
            (user),
            ...
        ]
    }

Parameters:

 * `username` (_optional_): username to filter. Usernames start with the query will be returned.
 * `user_email` (_optional_): email to filter. Requires `admincp` scope.

Required scopes:

 * `read`
 * `admincp`

### GET `/users/:userId`
Detail information of a user.

    {
        user: {
            user_id: (int),
            username: (string),
            user_title: (string),
            user_message_count: (int),
            user_register_date: (unix timestamp in seconds),
            user_last_seen_date: (unit timestamp in seconds), # since forum-2015080601
            user_like_count: (int),
            user_is_visitor: (boolean), # since forum-2013110601
            *user_email: (email),
            *user_dob_day: (int),
            *user_dob_month: (int),
            *user_dob_year: (int),
            *user_timezone_offset: (int),
            *user_has_password: (boolean),
            *user_unread_conversation_count: (int), # since forum-2014022601, requires conversate scope
            user_followers_total: (int), # must be in fields_include to work, since forum-2016062701
            user_followings_total: (int), # must be in fields_include to work, since forum-2016062701
            user_is_valid: (boolean),
            user_is_verified: (boolean),
            user_is_followed: (boolean), # since forum-2014052902
            user_is_ignored: (boolean), # since forum-2015072303
            *fields: [ # since forum-2017040402
                {
                    id: (string),
                    title: (string),
                    description: (string),
                    display_group: (string),
                    choices: [
                        {
                            key: (string),
                            value: (string),
                        },
                        ...
                    ],
                    is_multiple_choice: (boolean),
                    is_required: (boolean),
                    value: (string),
                    values: [
                        {
                            key: (string),
                            value: (string),
                        },
                        ...
                    ]
                },
                ...
            ],
            *user_groups: [ # since forum-2014092301
                {
                    user_group_id: (int),
                    user_group_title: (string),
                    is_primary_group: (boolean)
                },
                ...
            ],
            *user_external_authentications: [ # since forum-2017071001
                {
                    provider: (string),
                    provider_key: (string),
                }
                ...
            ],
            links: {
                permalink: (uri),
                detail: (uri),
                avatar: (uri),
                avatar_big: (uri), # since forum-2014052901
                avatar_small: (uri), # since forum-2015071001
                followers: (uri),
                followings: (uri),
                ignore: (uri) # since forum-2015072303
            },
            permissions: {
                edit: (boolean), # since forum-2015101201
                follow: (boolean),
                ignore: (boolean) # since forum-2015072303
            },
            *self_permissions: {
                create_conversation: (boolean),
                upload_attachment_conversation: (boolean) # since forum-2014081801
            },
            *edit_permissions: { # since forum-2015101201
                password: (boolean),
                user_email: (boolean),
                username: (boolean),
                user_title: (boolean),
                primary_group_id: (boolean),
                secondary_group_ids: (boolean),
                user_dob_day: (boolean),
                user_dob_month: (boolean),
                user_dob_year: (boolean),
                fields: (boolean) # since forum-2017040402
            }
        },
        subscription_callback: (uri) # since subscription-2014092301
    }

Fields with asterisk (*) are protected data. They are only included when the authenticated user is the requested user or the authenticated user is an admin with `user` admin permission and has `admincp` scope.

Parameters:

 * N/A

Required scopes:

 * `read`

### PUT `/users/:userId`
Edit a user. Since forum-2015041501. The introduction of this method makes POST `/users/:userId/password` deprecated.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `password` (_optional_): data of the new password.
 * `password_old` (_optional_): data of the existing password, it is not required if (1) the current authenticated user has `user` admin permission, (2) the `admincp` scope is granted and (3) the user being edited is not the current authenticated user.
 * `password_algo` (_optional_): algorithm used to encrypt the `password` and `password_old` parameters. See [Encryption](#encryption) section for more information.
 * `user_email` (_optional_): new email of the user.

 * `username` (_optional_): new username of the user. Changing username requires Administrator permission.
 * `user_title` (_optional_): new custom title of the user. Changing user title requires Administrator permission. Since forum-2015101201.
 * `primary_group_id` (_optional_): id of new primary group. Since forum-2015100702.
 * `secondary_group_ids` (_optional_): array of ids of new secondary groups. Since forum-2015100702.

 * `user_dob_day` (_optional_): new date of birth (day) of the user. This parameter must come together with `user_dob_month` and `user_dob_year`. User can add his/her own date of birth but changing existing data requires Administrator permission.
 * `user_dob_month` (_optional_): new date of birth (month) of the user.
 * `user_dob_year` (_optional_): new date of birth (year) of the user.

 * `fields` (_optional_): array of values for user fields. If the field is not of choice type, the value should be a string. If the field is single choice, the value should be a key of the choice. If the field is multiple choice, the value should be an array of keys. Since forum-2017040402.

Required scopes:

 * `post`
 * `admincp`

### POST `/users/:userId/avatar`
Upload avatar for a user.

    {
        status: "ok",
        message: "Upload completed successfully"
    }

Parameters:

 * avatar (__required__): binary data of the avatar.

Required scopes:

 * `post`

### DELETE `/users/:userId/avatar`
Delete avatar for a user.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/users/:userId/followers`
List of a user's followers

    {
        users: [
            {
                user_id: (int),
                username: (string)
            },
            ...
        ]
    }

Parameters:

 * `total` (_optional_): if included in the request, only the user count is returned as `users_total`. Since forum-2015072305.

Required scopes:

 * `read`

### POST `/users/:userId/followers`
Follow a user.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### DELETE `/users/:userId/followers`
Un-follow a user.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/users/:userId/followings`
List of users whom are followed by a user.

    {
        users: [
            {
                user_id: (int),
                username: (string)
            },
            ...
        ]
    }

Parameters:

 * `total` (_optional_): if included in the request, only the user count is returned as `users_total`. Since forum-2015072305.

Required scopes:

 * `read`

 ### GET `/users/ignored`
 List of a ignored users of current user. Since forum-2015072303.

     {
         users: [
             {
                 user_id: (int),
                 username: (string)
             },
             ...
         ]
     }

 Parameters:

  * `total` (_optional_): if included in the request, only the user count is returned as `users_total`. Since forum-2015072305.

 Required scopes:

  * `read`

 ### POST `/users/:userId/ignore`
 Ignore a user. Since forum-2015072303.

     {
         status: "ok",
         message: "Changes Saved"
     }

 Parameters:

  * N/A

 Required scopes:

  * `post`

 ### DELETE `/users/:userId/ignore`
 Stop ignoring a user. Since forum-2015072303.

     {
         status: "ok",
         message: "Changes Saved"
     }

 Parameters:

  * N/A

 Required scopes:

  * `post`

### GET `/users/groups`
List of all user groups. Since forum-2014092301.

    {
        user_groups: [
            {
                user_group_id: (int),
                user_group_title: (string)
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`
 * `admincp`

### GET `/users/:userId/groups`
List of a user's groups. Since forum-2014092301.

    {
        user_groups: [
            {
                user_group_id: (int),
                user_group_title: (string),
                is_primary_group: (boolean)
            },
            ...
        ],
        user_id: (int)
    }

Parameters:

 * N/A

Required scopes:

 * `read`
 * `admincp` (not required if viewing groups of current authenticated user)

### GET `/users/me`
Alias for GET `/users/:userId` for authorized user.

### PUT `/users/me`
Alias for PUT `/users/:userId` for authorized user.

### POST `/users/me/avatar`
Alias for POST `/users/:userId/avatar` for authorized user.

### DELETE `/users/me/avatar`
Alias for DELETE `/users/:userId/avatar` for authorized user.

### GET `/users/me/followers`
Alias for GET `/users/:userId/followers` for authorized user.

### GET `/users/me/followings`
Alias for GET `/users/:userId/followings` for authorized user.

### GET `/users/me/groups`
Alias for GET `/users/:userId/groups` for authorized user. Since forum-2014092301.

## Profile Posts

### GET `/users/:userId/timeline`
List of contents created by user (with pagination). Since forum-2015042001.

    {
        profile_posts: nil, # removed since forum-2015080503
        profile_posts_total: nil, # removed since forum-2015080503
        data: [ # since forum-2015080503
            (content),
            ...
        ],
        data_total: (int), # since forum-2015080503
        user: (user), # since forum-2015080503
        links {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `page` (_optional_): page number of profile posts.
 * `limit` (_optional_): number of profile posts in a page. Default value depends on the system configuration.

Required scopes:

 * `read`

### POST `/users/:userId/timeline`
Create a new profile post on a user timeline. Since forum-2015042001.

    {
        profile_post: (profile_post)
    }

Parameters:

 * `post_body` (__required__): content of the new profile post.
 * `status` (_optional_, since forum-2015091103): if posting to authorized user timeline, `status` can be used instead of `post_body` for content of the new profile post.

Required scopes:

 * `post`


### GET `/users/me/timeline`
Alias for GET `/users/:userId/timeline` for authorized user. Since forum-2015042001.

### POST `/users/me/timeline`
Alias for POST `/users/:userId/timeline` for authorized user. Since forum-2015042001.

### GET `/profile-posts/:profilePostId`
Detail information of a profile post. Since forum-2015042001.

    {
        profile_post: {
            profile_post_id: (int),
            timeline_user_id: (int),
            timeline_username: (string), # since forum-2016062002
            poster_user_id: (int),
            poster_username: (string),
            user_is_ignored: (boolean), # since forum-2015072304
            post_create_date: (unix timestamp in seconds),
            post_body: (string),
            post_like_count: (int),
            post_comment_count: (int),
            post_is_published: (boolean),
            post_is_deleted: (boolean),
            post_is_liked: (boolean),
            latest_comments: [ # must be in fields_include to work, since forum-2016061802
                (profile_post > comment),
                ...
            ],
            links: {
                permalink: (uri),
                detail: (uri),
                timeline: (uri),
                timeline_user: (uri),
                poster: (uri),
                likes: (uri),
                comments: (uri),
                report: (uri),
                poster_avatar: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                like: (boolean),
                comment: (boolean),
                report: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### PUT `/profile-posts/:profilePostId`
Edit a profile post. Since forum-2015042001.

    {
        profile_post: (profile_post)
    }

Parameters:

 * `post_body` (__required__): new content of the profile post.

Required scopes:

 * `post`

### DELETE `/profile-posts/:profilePostId`
Delete a profile post. Since forum-2015042001.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `reason` (_optional_, since forum-2016042501): reason of the profile post removal.

Required scopes:

 * `post`


### GET `/profile-posts/:profilePostId/likes`
List of users who liked a profile post. Since forum-2015042001.

    {
        users: [
            {
                user_id: (int),
                username: (string)
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### POST `/profile-posts/:profilePostId/likes`
Like a profile post. Since forum-2015042001.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### DELETE `/profile-posts/:profilePostId/likes`
Unlike a profile post. Since forum-2015042001.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/profile-posts/:profilePostId/comments`
List of comments of a profile post. Since forum-2015042001.

    {
        comments: [
            (profile_post > comment),
            ...
        ],
        links: { # since forum-2015081001
            prev: (uri),
            latest: (uri)
        },
        profile_post: (profile_post), # since forum-2015081001
        timeline_user: (user) # since forum-2015081001
    }

Parameters:

 * `before` (_optional_): date to get older comments. Since forum-2015081001. Please note that this entry point does not support the `page` parameter but it still does support `limit`.

Required scopes:

 * `read`

### POST `/profile-posts/:profilePostId/comments`
Create a new profile post comment. Since forum-2015042001.

    {
        comment: (profile_post > comment)
    }

Parameters:

 * `comment_body` (__required__): content of the new profile post comment.

Required scopes:

 * `post`

### GET `/profile-posts/:profilePostId/comments/:commentId`
Detail information of a profile post comment. Since forum-2015042001.

	{
		comment: {
			comment_id: (int),
			timeline_user_id: (int),
			profile_post_id: (int),
			comment_user_id: (int),
			comment_username: (string),
            user_is_ignored: (boolean), # since forum-2015072304
			comment_create_date: (unix timestamp in seconds),
			comment_body: (string),
			links: {
				detail: (uri),
				profile_post: (uri),
				timeline: (uri),
				timeline_user: (uri),
				poster: (uri),
				poster_avatar: (uri)
			},
			permissions: {
				view: (boolean),
				delete: (boolean)
			}
		}
	}

Parameters:

 * N/A

Required scopes:

 * `read`

### DELETE `/profile-posts/:profilePostId/comments/:commentId`
Delete a profile post's comment. Since forum-2015042001.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### POST `/profile-posts/:profilePostId/report`
Report a profile post. Since forum-2015042001.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `message` (__required__): reason of the report.

Required scopes:

 * `post`

## Conversations

### GET `/conversations`
List of conversations (with pagination).

    {
        conversations: [
            (conversation),
            (conversation),
            ...
        ],
        conversations_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `page` (_optional_): page number of conversations.
 * `limit` (_optional_): number of conversations in a page. Default value depends on the system configuration.

Required scopes:

 * `read`
 * `conversate`

### POST `/conversations`
Create a new conversation.

    {
        conversation: (conversation)
    }

Parameters:

 * `conversation_title` (__required__): title of the new conversation.
 * `recipients` (__required__): usernames of recipients of the new conversation. Separated by comma.
 * `message_body` (__required__): content of the new conversation.

Required scopes:

 * `post`
 * `conversate`

### GET `/conversations/:conversationId`
Detail information of a conversation.

    {
        conversation: {
            conversation_id: (int),
            conversation_title: (string),
            creator_user_id: (int),
            creator_username: (string),
            user_is_ignored: (boolean), # since forum-2015072304
            conversation_create_date: (unix timestamp in seconds),
            conversation_update_date: (unix timestamp in seconds),
            conversation_message_count: (int),
            conversation_has_new_message: (boolean),
            conversation_is_open: (boolean),
            conversation_is_deleted: (boolean),
            first_message: {conversation-message},
            recipients: [
                {
                    user_id: (int),
                    username: (string)
                },
                {
                    user_id: (int),
                    username: (string)
                },
                ...
            ]
            links: {
                permalink: (uri),
                detail: (uri),
                messages: (uri)
            },
            permissions: {
                reply: (boolean),
                delete: (boolean),
                upload_attachment: (boolean) # since forum-2014081801
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`
 * `conversate`

### DELETE `/conversations/:conversationId`
Delete a conversation.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`
 * `conversate`

### POST `/conversations/attachments`
Upload an attachment for a conversation. Since forum-2014053003.

    {
        attachment: (conversation-message > attachment)
    }

Parameters:

* `file` (__required__): binary data of the attachment.
* `attachment_hash` (_optional_): a unique hash value.

Required scopes:

* `post`

### DELETE `/conversations/attachments`
Delete an attachment for a conversation. Since forum-2014053003.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `attachment_id` (__required__): id of the attachment.
 * `attachment_hash` (_optional_): the hash that was used when the attachment was uploaded (use only if the attachment hasn't been associated with a conversation).

Required scopes:

 * `post`

### GET `/conversation-messages`
List of messages in a conversation (with pagination).

    {
        messages: [
            (conversation-message),
            (conversation-message),
            ...
        ],
        messages_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        },
        conversation: (conversation) # since forum-2015081002
    }

Parameters:

 * `conversation_id` (__required__): id of needed conversation.
 * `page` (_optional_): page number of messages.
 * `limit` (_optional_): number of messages in a page. Default value depends on the system configuration.
 * `order` (_optional_, since forum-2015102101): ordering of messages. Support `natural`, `natural_reverse`.

Required scopes:

 * `read`
 * `conversate`

### POST `/conversation-messages`
Create a new conversation message.

    {
        message: (conversation-message)
    }

Parameters:

 * `conversation_id` (__required__): id of the target conversation.
 * `message_body` (__required__): content of the new message.

Required scopes:

 * `post`
 * `conversate`

### POST `/conversation-messages/attachments`
Upload an attachment for a message. The attachment will be associated after the message is saved. Since forum-2014053003.

    {
        attachment: (conversation-message > attachment)
    }

Parameters:

 * `file` (__required__): binary data of the attachment.
 * `conversation_id` (_optional_): id of the container conversation of the target message.
 * `message_id` (_optional_): id of the target message.
 * `attachment_hash` (_optional_): a unique hash.

Parameters Note: either `conversation_id` or `message_id` parameter must has a valid id. Simply speaking, `conversation_id` must be used with POST `/conversation-message` (creating a new message) while `message_id` must be used with PUT `/conversation-messages/:messageId` (editing a message).

Required scopes:

* `post`

### GET `/conversation-messages/:messageId`
Detail information of a message.

    {
        message: {
            message_id: (int),
            conversation_id: (int),
            creator_user_id: (int),
            creator_username: (string),
            user_is_ignored: (boolean), # since forum-2015072304
            message_create_date: (unix timestamp in seconds),
            message_body: (string),
            message_body_html: (string),
            message_body_plain_text: (string),
            signature: (string), # since forum-2014082801
            signature_html: (string), # since forum-2014082801
            signature_plain_text: (string), # since forum-2014082801
            message_account_count: (int),
            attachments: [  # since forum-2014053003
                {
                    attachment_id: (int),
                    message_id: (int),
                    attachment_download_count: (int),
                    filename: (string),
                    attachment_is_inserted: (boolean), # since forum-2014091001
                    links: {
                        permalink: (uri),
                        data: (uri),
                        thumbnail: (uri)
                    },
                    permissions: {
                        view: (boolean),
                        delete: (boolean) # since forum-2014081801
                    }
                },
                ...
            ],
            links: {
                detail: (uri),
                conversation: (uri),
                creator: (uri),
                creator_avatar: (uri),
                report: (uri) # since forum-2015081101
            },
            permissions: { # since forum-2014053101
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                reply: (boolean),
                upload_attachment: (boolean), # since forum-2014081801
                report: (uri) # since forum-2015081101
            }
        }
    }

### PUT `/conversation-messages/:messageId`
Edit a message. Since forum-2014053101.

    {
        message: (convesation-message)
    }

Parameters:

 * `message_body` (__required__): new content of the message.

Required scopes:

 * `post`

### DELETE `/conversation-messages/:messageId`
Delete a message.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/conversation-messages/:messageId/attachments`
List of attachments of a message. Since forum-2014053003.

    {
        attachments: [
            (conversation-message > attachment),
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### GET `/conversation-messages/:messageId/attachments/:attachmentId`
Binary data of a message's attachment. Since forum-2014053003.

Parameters:

 * `max_width` (_optional_): maximum width required (applicable for image attachment only).
 * `max_height` (_optional_): maximum height required (applicable for image attachment only).
 * `keep_ratio` (_optional_): whether to keep original ratio during resizing (applicable for image attachment only).

Required scopes:

 * `read`

### DELETE `/conversation-messages/:messageId/attachments/:attachmentId`
Delete a message's attachment. Since forum-2014053003.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `conversation_id` (_optional_): id of the container thread of the target post (use only if the attachment hasn't been associated with a message).
 * `attachment_hash` (_optional_): the hash that was used when the attachment was uploaded (use only if the attachment hasn't been associated with a message).

Required scopes:

 * `post`

### POST `/conversation-messages/:messageId/report`
Report a message. Since forum-2015081101.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `message` (__required__): reason of the report.

Required scopes:

 * `post`

## Notifications

### GET `/notifications`
List of notifications (both read and unread). Since forum-2014022602.

    {
        notifications: [
            {
                notification_id: (int),
                notification_create_date: (unix timestamp in seconds),
                notification_is_unread: (boolean), # since forum-2015072301
                creator_user_id: (int), # since subscription-2014081001
                creator_username: (string), # since subscription-2014081001
                notification_type: (string), # since forum-2014080901
                notification_html: (string),
                links: {
                    content: (uri), # since forum-2015041001
                    creator_avatar: (uri), # since forum-2015080501
                }
            },
            ...
        ],
        subscription_callback: (uri) # since subscription-2014081002
    }

Required scopes:

 * `read`

### GET `/notifications/content`
Get associated content of notification. The response depends on the content type. Since forum-2015041001.

Parameters:

 * `notification_id` (__required__): id of the notification.

Required scopes:

 * `read`

### POST `/notifications/read`
Mark all existing notifications as read. Since forum-2014092701.

    {
        status: "ok",
        message: "Changes Saved"
    }

Required scopes:

 * `post`

## Searching

### POST `/search/threads`
Search for threads.

    {
        threads: [
            {
				thread_id: (int)
            },
            ...
        ],
        data: [ # since forum-2015032403
            (thread),
            ...
        ]
    }

Parameters:

 * `q` (__required__): query to search for.
 * `limit` (_optional_): maximum number of results. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for contents. Child forums of the specified forum will be included in the search.
 * `user_id` (_optional_): id of the creator to search for contents. Since forum-2015041502.
 * `data_limit` (_optional_): number of thread data to be returned. By default, no data is returned. Since forum-2015032403.

Required scopes:

 * `read`

### POST `/search/posts`
Search for posts.

    {
        posts: [
            {
				post_id: (int)
            },
            ...
        ],
        data: [ # since forum-2015032403
            (post),
            ...
        ]
    }

Parameters:

 * `q` (__required__): query to search for.
 * `limit` (_optional_): maximum number of results. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for contents. Child forums of the specified forum will be included in the search.
 * `thread_id` (_optional_): id of the container thread to search for posts.
 * `user_id` (_optional_): id of the creator to search for contents. Since forum-2015041502.
 * `data_limit` (_optional_): number of post data to be returned. By default, no data is returned. Since forum-2015032403.

Required scopes:

 * `read`

### POST `/search/profile-posts`
Alias for POST `/search` but only search profile posts. Since forum-2015042001.

### POST `/search`
Search for all supported contents. Since forum-2015042002.

    {
        data: [
            (content),
            ...
        ],
        data_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `q` (__required__): query to search for.
 * `tag` (_optional_): tag to search for tagged contents. Since forum-2015091001.
 * `forum_id` (_optional_): id of the container forum to search for contents. Child forums of the specified forum will be included in the search.
 * `user_id` (_optional_): id of the creator to search for contents.
 * `page` (_optional_): page number of results. Since forum-2015042301.
 * `limit` (_optional_): number of results in a page. Default value depends on the system configuration. Since forum-2015042301.

Required scopes:

 * `read`

### POST `/search/tagged`
Search for tagged contents. Since forum-2015091001.

    {
        data: [
            (content),
            ...
        ],
        data_total: (int),
        search_tags: {
          (tag_id): (tag_text),
          ...
        }
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }
Parameters:

 * `tag` (__required__): tag to search for tagged contents.
 * `tags` (_optional_): array of tags to search for tagged contents. Since forum-2017050201.
 * `page` (_optional_): page number of results. Since forum-2015042301.
 * `limit` (_optional_): number of results in a page. Default value depends on the system configuration. Since forum-2015042301.

Required scopes:

 * `read`

## Batch requests

### POST `/batch`
Execute multiple API requests at once.

    {
        jobs: {
            (job_id): {
                _job_result: (ok|error|message),
                _job_error: (string),
                _job_message: (string),
                ...
            },
            ...
        }
    }

JSON POST body:

    [
        {
            id: (string),
            uri: (uri),
            method: (DELETE|GET|POST|PUT),
            params: {
                (key): (value),
                ...
            }
        },
        ...
    ]

Parameters (for a single job):

 * `id` (_optional_): identifier for the job, will be use in output as key of a result set. If this parameter is not set, the URI will be used.
 * `uri` (__required__): URI of the API request to execute.
 * `method` (_optional_): HTTP method of the API request to execute. If this parameter is not set, GET HTTP method will be used.
 * `params` (_optional_): parameters of the API request to execute.

Required scopes:

 * N/A

### POST `/search/indexing`
Index external content data into search system to be searched later. Client must have the option "Can index search contents" enabled to use this feature. Since search/indexing-2015091501.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `content_type` (__required__)
 * `content_id` (__required__)
 * `title` (__required__)
 * `body` (__required__)
 * `date` (_optional_): unix timestamp in second of the content. If missing, current time will be used.
 * `link` (__required__)

Required scopes:

 * `post`

## Subscriptions
Clients can subscribe to certain events to receive real time ping when data is changed within the system. The subscription system uses the [PubSubHubbub protocol](https://code.google.com/p/pubsubhubbub/) to communicate with hubs and subscribers. Since subscription-2014081001.

List of supported topics:

 * `user_x` (x is the user_id of the interested user): receives ping when user data is inserted, updated or deleted. The registered callback will be included in GET `/users/:userId` as parameter `subscription_callback`.
 * `user_notification_x` (x is the user_id of the interested user): receives ping when user gets a new notification. Notification data will be included in the ping. The registered callback will be included in GET `/notifications` as parameter `subscription_callback`.
 * `thread_post_x` (x is the thread_id of the interested thread): receives ping when a post in the thread is inserted, updated or deleted. The registered callback will be included in GET `/posts?thread_id=x` as parameter `subscription_callback`.

For supported resources, two `Link` HTTP headers will be included. It is recommended to check for these headers before issuing subscribe request because webmaster can disable some or all types of subscriptions.

    Link: <topic url>; rel="self"
    Link: <hub url>; rel="hub"

### Example subscribe request

    curl -XPOST http://domain.com/api/subscriptions \
        -d 'oauth_token=$token' \
        -d 'hub.callback=$callback_url' \
        -d 'hub.mode=subscribe' \
        -d 'hub.topic=$topic'
