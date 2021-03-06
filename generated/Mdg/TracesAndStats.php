<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: reports.proto

namespace Mdg;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * A sequence of traces and stats. An individual trace should either be counted as a stat or trace
 *
 * Generated from protobuf message <code>mdg.engine.proto.TracesAndStats</code>
 */
class TracesAndStats extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>repeated .mdg.engine.proto.Trace trace = 1;</code>
     */
    private $trace;
    /**
     * Generated from protobuf field <code>repeated .mdg.engine.proto.ContextualizedStats stats_with_context = 2;</code>
     */
    private $stats_with_context;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Mdg\Trace[]|\Google\Protobuf\Internal\RepeatedField $trace
     *     @type \Mdg\ContextualizedStats[]|\Google\Protobuf\Internal\RepeatedField $stats_with_context
     * }
     */
    public function __construct($data = NULL) {
        \Metadata\Reports::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>repeated .mdg.engine.proto.Trace trace = 1;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getTrace()
    {
        return $this->trace;
    }

    /**
     * Generated from protobuf field <code>repeated .mdg.engine.proto.Trace trace = 1;</code>
     * @param \Mdg\Trace[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setTrace($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \Mdg\Trace::class);
        $this->trace = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>repeated .mdg.engine.proto.ContextualizedStats stats_with_context = 2;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getStatsWithContext()
    {
        return $this->stats_with_context;
    }

    /**
     * Generated from protobuf field <code>repeated .mdg.engine.proto.ContextualizedStats stats_with_context = 2;</code>
     * @param \Mdg\ContextualizedStats[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setStatsWithContext($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \Mdg\ContextualizedStats::class);
        $this->stats_with_context = $arr;

        return $this;
    }

}

