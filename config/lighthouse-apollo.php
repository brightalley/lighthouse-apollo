<?php

return [
    /**
     * The service: scoped API key for your Apollo Studio account.
     */
    'apollo_key' => env('APOLLO_KEY'),

    /**
     * The name of your graph in Apollo Studio (e.g., docs-example-graph).
     */
    'apollo_graph_id' => env('APOLLO_GRAPH_ID'),

    /**
     * The name for the current graph variant.
     */
    'apollo_graph_variant' => env('APOLLO_GRAPH_VARIANT', 'current'),

    /**
     * The host name for this GraphQL server.
     */
    'hostname' => env('APOLLO_HOSTNAME', gethostname()),

    /**
     * Which endpoint to send tracing information to.
     */
    'tracing_endpoint' => 'https://usage-reporting.api.apollographql.com/api/ingress/traces',

    /**
     * Which endpoint to send schema registrations.
     */
    'schema_reporting_endpoint' => 'https://schema-reporting.api.apollographql.com/api/graphql',

    /**
     * How and when to send tracing results.
     *
     * Options are:
     * - sync: Send the tracing to Apollo Studio in the same request.
     * - redis: Aggregate tracing results in Redis, and send later via a command.
     * - database: Aggregate tracing results in the database, and send later via a command.
     *
     * Note: database is not yet supported.
     */
    'send_tracing_mode' => 'sync',

    /**
     * What redis connection to use. Only used when 'send_tracing_mode' is set to redis.
     */
    'redis_connection' => 'default',

    /**
     * Whether to remove the tracing information from the extensions key in the GraphQL response.
     */
    'mute_tracing_extensions' => env('APP_DEBUG', false) !== true,

    /**
     * Whether to send the request headers to Apollo Studio.
     */
    'include_request_headers' => true,

    /**
     * Which request headers to not include in the HTTP information for a tracing. Should be lowercase.
     */
    'excluded_request_headers' => ['authentication', 'cookie', 'set-cookie'],

    /**
     * Whether to send query/mutation variables to Apollo Studio.
     */
    'include_variables' => true,

    /**
     * Which variables to include if include_variables is true. If `variables_only_names` has any values,
     * only variables with those names will be sent, the rest will be masked in the variables sent to
     * Apollo. If `variables_except_names` has any values, those will be masked.
     * The `except` setting has greater priority than `only`. In other words, if a key such as 'token' is
     * present in both only `variables_only_names` and in `variables_except_names`, the value will still
     * be masked.
     */
    'variables_only_names' => [],
    'variables_except_names' => ['password', 'username', 'email', 'token'],
];
