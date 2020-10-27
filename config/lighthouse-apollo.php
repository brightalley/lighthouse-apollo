<?php

return [
    /**
     * The service: scoped API key for your Apollo Studio account.
     */
    'apollo_key' => env('APOLLO_KEY'),

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
     * Which request headers to not include in the HTTP information for a tracing. Should be lowercase.
     */
    'excluded_request_headers' => ['authentication'],
];
