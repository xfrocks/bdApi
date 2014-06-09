# API Documents

## Authorization
The system follows OAuth2 specification [IETF draft v10](http://tools.ietf.org/html/draft-ietf-oauth-v2-10).

### Supported scopes
 * `read`
 * `post`
 * `usercp`
 * `conversate`
 * `admincp`

### Supported grant types
 * Authorization code
 * User credentials (username / password)
 * Refresh token

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

### Discoverability
System information and availability can be determined by sending a GET request to `/` (index route). A list of resources will be returned. If the request is authenticated, the revisions of API system and installed modules will also made available for further inspection.

## Common Parameters

### Fields filtering
For API method with resource data like a forum or a thread, the data can be filtered to get interested fields only. When there are no filter 

 * `fields_include`: coma-separated list of fields of a resource. If this parameter is used along with `fields_exclude`, the other parameter will be ignored.
 * `fields_exclude`: coma-separated list of fields of a resource to exclude in the response. Cannot be used with `fields_include` or this parameter will be ignored.

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
        categories_count: (int)
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
        forums_count: (int)
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
            forum_is_follow: (boolean), // since forum-2014053001
            links: {
                permalink: (uri),
                detail: (uri),
                followers: (uri), // since forum-2014053001
                sub-categories: (uri),
                sub-forums: (uri),
                threads: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                follow: (boolean), // since forum-2014053001
                create_thread: (boolean)
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

 * N/A

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
        }
    }

Parameters:

 * `forum_id` (__required__): ids of needed forums (separated by comma). Support for multiple ids were added in forum-2014011801.
 * `sticky` (_optional_): filter to get sticky threads only. If `sticky` = 1, `page` and `limit` parameters will be ignored.
 * `page` (_optional_): page number of threads.
 * `limit` (_optional_): number of threads in a page. Default value depends on the system configuration.
 * `order` (_optional_): ordering of threads. Support `natural`, `thread_create_date`, `thread_create_date_reverse`, `thread_update_date`, `thread_update_date_reverse`.

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
            thread_create_date: (unix timestamp in seconds),
            thread_update_date: (unix timestamp in seconds),
            thread_is_published: (boolean),
            thread_is_deleted: (boolean),
            thread_is_sticky: (boolean),
            thread_is_followed: (boolean), // since forum-2014052903
            first_post: (post),
            links: {
                permalink: (uri),
                detail: (uri),
                forum: (uri),
                posts: (uri),
                first_poster: (uri),
                first_post: (uri),
                last_poster: (uri),
                last_post: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                follow: (boolean), // since forum-2014052903
                post: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### DELETE `/threads/:threadId`
Delete a thread.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

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
        ]
    }

Parameters:

 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.

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
        ]
    }

Parameters:

 * `days` (_optional_): maximum number of days to search for threads.
 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.

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
        }
    }

Parameters:

 * `thread_id` (__required__): id of needed thread.
 * `page` (_optional_): page number of posts.
 * `limit` (_optional_): number of threads in a page. Default value depends on the system configuration.
 * `order` (_optional_, since forum-2013122401): ordering of posts. Support `natural`, `natural_reverse`.

Required scopes:

 * `read`

### POST `/posts`
Create a new post.

    {
        post: (post)
    }

Parameters:

 * `thread_id` (__required__): id of the target thread.
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
            post_create_date: (unix timestamp in seconds),
            post_body: (string),
            post_body_html: (string),
            post_body_plain_text: (string),
            post_like_count: (int),
            post_attachment_count: (int),
            post_is_published: (boolean),
            post_is_deleted: (boolean),
            post_is_first_post: (boolean), # since 2013122402
            post_is_liked: (boolean),
            attachments: [
                {
                    attachment_id: (int),
                    post_id: (int),
                    attachment_download_count: (int),
                    filename: (string), # since 2014052201
                    links: {
                        permalink: (uri),
                        data: (uri),
                        thumbnail: (uri)
                    },
                    permissions: {
                        view: (boolean)
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
                poster_avatar: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                reply: (boolean), #since forum-2014052901
                like: (boolean)
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
 * `thread_title` (_optional_, since forum-2014052203): new title of the thread (only used if the post is the first post in the thread and the user can edit thread).

Required scopes:

 * `post`

### DELETE `/posts/:postId`
Delete a post.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

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

 * `email` (__required__): email of the new user.
 * `username` (__required__): username of the new user.
 * `password` (__required__): password of the new user.
 * `password_algo` (_optional_): algorithm used to encrypt the `password` parameter. See [Encryption](#encryption) section for more information.
 * `user_dob_day` (_optional_): date of birth (day) of the new user.
 * `user_dob_month` (_optional_): date of birth (month) of the new user.
 * `user_dob_year` (_optional_): date of birth (year) of the new user.
 * `client_id` (_optional_): client ID of the Client. This parameter is required if the request is unauthorized (no `oauth_token`).

Required scopes:

 * `post`

### GET `/users/:userId`
Detail information of a user.

    {
        user: {
            user_id: (int),
            username: (string),
            user_title: (string),
            user_message_count: (int),
            user_register_date: (unix timestamp in seconds),
            user_like_count: (int),
            user_is_visitor: (boolean), // since 2013110601
            user_email: (email), // user_is_visitor==true only
            user_dob_day: (int), // user_is_visitor==true only
            user_dob_month: (int), // user_is_visitor==true only
            user_dob_year: (int), // user_is_visitor==true only
            user_timezone_offset: (int), // user_is_visitor==true only
            user_has_password: (boolean), // user_is_visitor==true only
            user_unread_conversation_count: (int), // since 2014022601, user_is_visitor==true only, requires conversate scope
            user_is_valid: (boolean),
            user_is_verified: (boolean),
            user_is_followed: (boolean), // since forum-2014052902
            user_custom_fields: { // user_is_visitor==true only, since 2013110601
                field_id: (field_value),
                ...
            }
            links: {
                permalink: (uri),
                detail: (uri),
                avatar: (uri),
                followers: (uri),
                followings: (uri)
            },
            permissions: {
                follow: (boolean)
            },
            self_permissions: { // user_is_visitor==true only
                create_conversation: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

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

 * N/A

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

 * N/A

Required scopes:

 * `read`

### POST `/users/:userId/password`
Change password of a user.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * `password_old` (__required__): data of the existing password.
 * `password` (__required__): data of the new password.
 * `password_algo` (_optional_): algorithm used to encrypt the `password` parameter. See [Encryption](#encryption) section for more information.

Required scopes:

 * `post`

### GET `/users/me`
Alias for GET `/users/:userId` for authorized user.

### POST `/users/me/avatar`
Alias for POST `/users/:userId/avatar` for authorized user.

### DELETE `/users/me/avatar`
Alias for DELETE `/users/:userId/avatar` for authorized user.

### GET `/users/me/followers`
Alias for GET `/users/:userId/followers` for authorized user.

### GET `/users/me/followings`
Alias for GET `/users/:userId/followings` for authorized user.

### POST `/users/me/password`
Alias for POST `/users/:userId/password` for authorized user.

## Conversation

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
                delete: (boolean)
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
        }
    }

Parameters:

 * `conversation_id` (__required__): id of needed conversation.
 * `page` (_optional_): page number of messages.
 * `limit` (_optional_): number of messages in a page. Default value depends on the system configuration.

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
            message_create_date: (unix timestamp in seconds),
            message_body: (string),
            message_body_html: (string),
            message_body_plain_text: (string),
            message_account_count: (int),
            attachments: [  # since forum-2014053003
                {
                    attachment_id: (int),
                    message_id: (int),
                    attachment_download_count: (int),
                    filename: (string),
                    links: {
                        permalink: (uri),
                        data: (uri),
                        thumbnail: (uri)
                    },
                    permissions: {
                        view: (boolean)
                    }
                },
                ...
            ],
            links: {
                detail: (uri),
                conversation: (uri),
                creator: (uri),
                creator_avatar: (uri)
            },
            permissions: { # since forum-2014053101
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                reply: (boolean)
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

## Notifications

### GET `/notifications`
List of conversations (with pagination). Since forum-2014022602.

    {
        notifications: [
            {
                notification_id: (int),
                notification_create_date: (unix timestamp in seconds),
                creator_user_id: (int),
                creator_username: (string),
                notification_html: (string)
            },
            ...
        ]
    }

Required scopes:

 * `read`


## Searching

### POST `/search/threads`
Search for threads.

    {
        threads: [
            {
                thread_id: (int)
            },
            ...
        ]
    }

Parameters:

 * `q` (__required__): query to search for.
 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.

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
        ]
    }

Parameters:

 * `q` (__required__): query to search for.
 * `limit` (_optional_): maximum number of result posts. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for posts. Child forums of the specified forum will be included in the search.
 * `thread_id` (_optional_): id of the container thread to search for posts.

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