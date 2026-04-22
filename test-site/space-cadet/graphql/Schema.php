<?php
/**
 * Space Cadet CMS — GraphQL Schema Definition
 *
 * Defines type field maps used by the Executor for field selection.
 * This is a lightweight registry — no resolver logic here.
 */
class GQLSchema {

    /**
     * Returns the allowed scalar fields for each type.
     * '*' means allow any field (used for JSON blobs like fields/values).
     */
    public static function fields(): array {
        return [
            'Collection' => [
                'id','name','slug','description','icon','supports_status',
                'is_singleton','sort_field','sort_direction','created_at','updated_at',
                'item_count','fields','items',
            ],
            'CollectionField' => [
                'id','collection_id','name','key','type','options','required','sort_order',
            ],
            'CollectionItem' => [
                'id','collection_id','title','slug','status','author_id','folder_id',
                'sort_order','published_at','created_at','updated_at','fields','labels',
            ],
            'Page' => [
                'id','title','slug','parent_id','status','template_id','author_id',
                'sort_order','meta_title','meta_desc','published_at','created_at','updated_at',
                'fields','fieldDefs','author_name',
            ],
            'GlobalGroup' => [
                'id','name','slug','description','created_at','updated_at','fields','values',
            ],
            'GlobalField' => [
                'id','group_id','name','key','type','options','sort_order',
            ],
            'Menu' => [
                'id','name','slug','created_at','updated_at','items',
            ],
            'MenuItem' => [
                'id','menu_id','parent_id','label','url','target','rel','icon',
                'link_type','linked_id','sort_order','children',
            ],
            'Media' => [
                'id','filename','original_name','mime_type','size_bytes','width','height',
                'folder_id','alt_text','caption','webp_path','thumb_path','uploaded_by','created_at',
                'url','thumb_url','webp_url',
            ],
            'Label' => ['id','name','slug','color'],
            'Folder' => ['id','name','parent_id','sort_order','children'],
            'User' => ['id','email','display_name','role','status','avatar_media_id','last_login_at','created_at'],
            'Form' => [
                'id','name','slug','description','success_message','notify_emails',
                'honeypot_field','rate_limit_max','created_at','updated_at','fields',
            ],
            'FormField' => ['id','form_id','name','key','type','placeholder','required','options','sort_order'],
            'SearchResult' => ['entity_type','entity_id','title','meta'],
            // Mutation results reuse entity types above
        ];
    }

    /**
     * Returns the set of valid root query fields.
     */
    public static function queryFields(): array {
        return [
            'collections','collection','collectionItem',
            'pages','page',
            'globals','global',
            'menus','menu',
            'media','mediaItem',
            'users','user',
            'labels','folders',
            'form','search',
        ];
    }

    /**
     * Returns the set of valid root mutation fields.
     */
    public static function mutationFields(): array {
        return [
            'createCollectionItem','updateCollectionItem','deleteCollectionItem',
            'createPage','updatePage','deletePage',
            'submitForm',
            'updateMedia','deleteMedia',
        ];
    }
}
