<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: reports.proto

namespace Mdg;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>mdg.engine.proto.QueryLatencyStats</code>
 */
class QueryLatencyStats extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>repeated sint64 latency_count = 13;</code>
     */
    private $latency_count;
    /**
     * Generated from protobuf field <code>uint64 request_count = 2;</code>
     */
    protected $request_count = 0;
    /**
     * Generated from protobuf field <code>uint64 cache_hits = 3;</code>
     */
    protected $cache_hits = 0;
    /**
     * Generated from protobuf field <code>uint64 persisted_query_hits = 4;</code>
     */
    protected $persisted_query_hits = 0;
    /**
     * Generated from protobuf field <code>uint64 persisted_query_misses = 5;</code>
     */
    protected $persisted_query_misses = 0;
    /**
     * Generated from protobuf field <code>repeated sint64 cache_latency_count = 14;</code>
     */
    private $cache_latency_count;
    /**
     * Generated from protobuf field <code>.mdg.engine.proto.PathErrorStats root_error_stats = 7;</code>
     */
    protected $root_error_stats = null;
    /**
     * Generated from protobuf field <code>uint64 requests_with_errors_count = 8;</code>
     */
    protected $requests_with_errors_count = 0;
    /**
     * Generated from protobuf field <code>repeated sint64 public_cache_ttl_count = 15;</code>
     */
    private $public_cache_ttl_count;
    /**
     * Generated from protobuf field <code>repeated sint64 private_cache_ttl_count = 16;</code>
     */
    private $private_cache_ttl_count;
    /**
     * Generated from protobuf field <code>uint64 registered_operation_count = 11;</code>
     */
    protected $registered_operation_count = 0;
    /**
     * Generated from protobuf field <code>uint64 forbidden_operation_count = 12;</code>
     */
    protected $forbidden_operation_count = 0;
    /**
     * The number of requests that were executed without field-level
     * instrumentation (and thus do not contribute to `observed_execution_count`
     * fields on this message's cousin-twice-removed FieldStats).
     *
     * Generated from protobuf field <code>uint64 requests_without_field_instrumentation = 17;</code>
     */
    protected $requests_without_field_instrumentation = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int[]|string[]|\Google\Protobuf\Internal\RepeatedField $latency_count
     *     @type int|string $request_count
     *     @type int|string $cache_hits
     *     @type int|string $persisted_query_hits
     *     @type int|string $persisted_query_misses
     *     @type int[]|string[]|\Google\Protobuf\Internal\RepeatedField $cache_latency_count
     *     @type \Mdg\PathErrorStats $root_error_stats
     *     @type int|string $requests_with_errors_count
     *     @type int[]|string[]|\Google\Protobuf\Internal\RepeatedField $public_cache_ttl_count
     *     @type int[]|string[]|\Google\Protobuf\Internal\RepeatedField $private_cache_ttl_count
     *     @type int|string $registered_operation_count
     *     @type int|string $forbidden_operation_count
     *     @type int|string $requests_without_field_instrumentation
     *           The number of requests that were executed without field-level
     *           instrumentation (and thus do not contribute to `observed_execution_count`
     *           fields on this message's cousin-twice-removed FieldStats).
     * }
     */
    public function __construct($data = NULL) {
        \Metadata\Reports::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>repeated sint64 latency_count = 13;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getLatencyCount()
    {
        return $this->latency_count;
    }

    /**
     * Generated from protobuf field <code>repeated sint64 latency_count = 13;</code>
     * @param int[]|string[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setLatencyCount($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::SINT64);
        $this->latency_count = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 request_count = 2;</code>
     * @return int|string
     */
    public function getRequestCount()
    {
        return $this->request_count;
    }

    /**
     * Generated from protobuf field <code>uint64 request_count = 2;</code>
     * @param int|string $var
     * @return $this
     */
    public function setRequestCount($var)
    {
        GPBUtil::checkUint64($var);
        $this->request_count = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 cache_hits = 3;</code>
     * @return int|string
     */
    public function getCacheHits()
    {
        return $this->cache_hits;
    }

    /**
     * Generated from protobuf field <code>uint64 cache_hits = 3;</code>
     * @param int|string $var
     * @return $this
     */
    public function setCacheHits($var)
    {
        GPBUtil::checkUint64($var);
        $this->cache_hits = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 persisted_query_hits = 4;</code>
     * @return int|string
     */
    public function getPersistedQueryHits()
    {
        return $this->persisted_query_hits;
    }

    /**
     * Generated from protobuf field <code>uint64 persisted_query_hits = 4;</code>
     * @param int|string $var
     * @return $this
     */
    public function setPersistedQueryHits($var)
    {
        GPBUtil::checkUint64($var);
        $this->persisted_query_hits = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 persisted_query_misses = 5;</code>
     * @return int|string
     */
    public function getPersistedQueryMisses()
    {
        return $this->persisted_query_misses;
    }

    /**
     * Generated from protobuf field <code>uint64 persisted_query_misses = 5;</code>
     * @param int|string $var
     * @return $this
     */
    public function setPersistedQueryMisses($var)
    {
        GPBUtil::checkUint64($var);
        $this->persisted_query_misses = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>repeated sint64 cache_latency_count = 14;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getCacheLatencyCount()
    {
        return $this->cache_latency_count;
    }

    /**
     * Generated from protobuf field <code>repeated sint64 cache_latency_count = 14;</code>
     * @param int[]|string[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setCacheLatencyCount($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::SINT64);
        $this->cache_latency_count = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.mdg.engine.proto.PathErrorStats root_error_stats = 7;</code>
     * @return \Mdg\PathErrorStats|null
     */
    public function getRootErrorStats()
    {
        return $this->root_error_stats;
    }

    public function hasRootErrorStats()
    {
        return isset($this->root_error_stats);
    }

    public function clearRootErrorStats()
    {
        unset($this->root_error_stats);
    }

    /**
     * Generated from protobuf field <code>.mdg.engine.proto.PathErrorStats root_error_stats = 7;</code>
     * @param \Mdg\PathErrorStats $var
     * @return $this
     */
    public function setRootErrorStats($var)
    {
        GPBUtil::checkMessage($var, \Mdg\PathErrorStats::class);
        $this->root_error_stats = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 requests_with_errors_count = 8;</code>
     * @return int|string
     */
    public function getRequestsWithErrorsCount()
    {
        return $this->requests_with_errors_count;
    }

    /**
     * Generated from protobuf field <code>uint64 requests_with_errors_count = 8;</code>
     * @param int|string $var
     * @return $this
     */
    public function setRequestsWithErrorsCount($var)
    {
        GPBUtil::checkUint64($var);
        $this->requests_with_errors_count = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>repeated sint64 public_cache_ttl_count = 15;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getPublicCacheTtlCount()
    {
        return $this->public_cache_ttl_count;
    }

    /**
     * Generated from protobuf field <code>repeated sint64 public_cache_ttl_count = 15;</code>
     * @param int[]|string[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setPublicCacheTtlCount($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::SINT64);
        $this->public_cache_ttl_count = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>repeated sint64 private_cache_ttl_count = 16;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getPrivateCacheTtlCount()
    {
        return $this->private_cache_ttl_count;
    }

    /**
     * Generated from protobuf field <code>repeated sint64 private_cache_ttl_count = 16;</code>
     * @param int[]|string[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setPrivateCacheTtlCount($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::SINT64);
        $this->private_cache_ttl_count = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 registered_operation_count = 11;</code>
     * @return int|string
     */
    public function getRegisteredOperationCount()
    {
        return $this->registered_operation_count;
    }

    /**
     * Generated from protobuf field <code>uint64 registered_operation_count = 11;</code>
     * @param int|string $var
     * @return $this
     */
    public function setRegisteredOperationCount($var)
    {
        GPBUtil::checkUint64($var);
        $this->registered_operation_count = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 forbidden_operation_count = 12;</code>
     * @return int|string
     */
    public function getForbiddenOperationCount()
    {
        return $this->forbidden_operation_count;
    }

    /**
     * Generated from protobuf field <code>uint64 forbidden_operation_count = 12;</code>
     * @param int|string $var
     * @return $this
     */
    public function setForbiddenOperationCount($var)
    {
        GPBUtil::checkUint64($var);
        $this->forbidden_operation_count = $var;

        return $this;
    }

    /**
     * The number of requests that were executed without field-level
     * instrumentation (and thus do not contribute to `observed_execution_count`
     * fields on this message's cousin-twice-removed FieldStats).
     *
     * Generated from protobuf field <code>uint64 requests_without_field_instrumentation = 17;</code>
     * @return int|string
     */
    public function getRequestsWithoutFieldInstrumentation()
    {
        return $this->requests_without_field_instrumentation;
    }

    /**
     * The number of requests that were executed without field-level
     * instrumentation (and thus do not contribute to `observed_execution_count`
     * fields on this message's cousin-twice-removed FieldStats).
     *
     * Generated from protobuf field <code>uint64 requests_without_field_instrumentation = 17;</code>
     * @param int|string $var
     * @return $this
     */
    public function setRequestsWithoutFieldInstrumentation($var)
    {
        GPBUtil::checkUint64($var);
        $this->requests_without_field_instrumentation = $var;

        return $this;
    }

}

