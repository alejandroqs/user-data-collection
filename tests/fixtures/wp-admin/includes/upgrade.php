<?php
// Test double: record the requested schema without accessing a database.
function dbDelta($sql) {
    $GLOBALS['requested_schema'] = $sql;
}
