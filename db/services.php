<?php

$functions = array(
    'local_block_myoverview_term_filter_get_enrolled_courses_by_term' => array( // local_PLUGINNAME_FUNCTIONNAME is the name of the web service function that the client will call.
        'classname'   => 'local_block_myoverview_term_filter_external', // create this class in local/PLUGINNAME/externallib.php
        'methodname'  => 'get_enrolled_courses_by_term', // implement this function into the above class
        'classpath'   => 'block/myoverview_term_filter/local/block_myoverview_term_filter/externallib.php',
        'description' => 'Creates a list of courses the user is enrolled in in the given term',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'  => 'moodle3.6',
    )
);