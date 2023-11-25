# single-page-wp-plugin
A simple WordPress plugin in a single page with custom table, shortcodes to insert and retrieve data and public REST API endpoints.

The plugin creates the `{prefix}_sp_wp_plugin` table, 2 shortcodes to handle the data into the table and 2 REST API endpoints. 

## Shortcodes 
- `[sp_wp_display_form]` - Display a form to input data in the custom table.
- `[sp_wp_display_list]` - Display the up to the last 10 submitted reviews and a box to search for reviews and filter the table.

## Public REST API Endpoints

### [GET] `/wp-json/review/v1/get`

Get reviews from the custom table. 

Possible params:
```
page (int)
per_page (int)
q (string) => search query
```

### [POST] `/wp-json/review/v1/add`

Add a new review on the custom table.

POST Data:

```
name (string)
email (string - email)
rating (int)
comment (string)
```
