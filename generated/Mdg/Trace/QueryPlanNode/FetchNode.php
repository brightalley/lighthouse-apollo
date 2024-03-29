<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: reports.proto

namespace Mdg\Trace\QueryPlanNode;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * This represents a node to send an operation to an implementing service
 *
 * Generated from protobuf message <code>mdg.engine.proto.Trace.QueryPlanNode.FetchNode</code>
 */
class FetchNode extends \Google\Protobuf\Internal\Message
{
    /**
     * XXX When we want to include more details about the sub-operation that was
     * executed against this service, we should include that here in each fetch node.
     * This might include an operation signature, requires directive, reference resolutions, etc.
     *
     * Generated from protobuf field <code>string service_name = 1;</code>
     */
    protected $service_name = '';
    /**
     * Generated from protobuf field <code>bool trace_parsing_failed = 2;</code>
     */
    protected $trace_parsing_failed = false;
    /**
     * This Trace only contains start_time, end_time, duration_ns, and root;
     * all timings were calculated **on the federated service**, and clock skew
     * will be handled by the ingress server.
     *
     * Generated from protobuf field <code>.mdg.engine.proto.Trace trace = 3;</code>
     */
    protected $trace = null;
    /**
     * relative to the outer trace's start_time, in ns, measured in the gateway.
     *
     * Generated from protobuf field <code>uint64 sent_time_offset = 4;</code>
     */
    protected $sent_time_offset = 0;
    /**
     * Wallclock times measured in the gateway for when this operation was
     * sent and received.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp sent_time = 5;</code>
     */
    protected $sent_time = null;
    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp received_time = 6;</code>
     */
    protected $received_time = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $service_name
     *           XXX When we want to include more details about the sub-operation that was
     *           executed against this service, we should include that here in each fetch node.
     *           This might include an operation signature, requires directive, reference resolutions, etc.
     *     @type bool $trace_parsing_failed
     *     @type \Mdg\Trace $trace
     *           This Trace only contains start_time, end_time, duration_ns, and root;
     *           all timings were calculated **on the federated service**, and clock skew
     *           will be handled by the ingress server.
     *     @type int|string $sent_time_offset
     *           relative to the outer trace's start_time, in ns, measured in the gateway.
     *     @type \Google\Protobuf\Timestamp $sent_time
     *           Wallclock times measured in the gateway for when this operation was
     *           sent and received.
     *     @type \Google\Protobuf\Timestamp $received_time
     * }
     */
    public function __construct($data = NULL) {
        \Metadata\Reports::initOnce();
        parent::__construct($data);
    }

    /**
     * XXX When we want to include more details about the sub-operation that was
     * executed against this service, we should include that here in each fetch node.
     * This might include an operation signature, requires directive, reference resolutions, etc.
     *
     * Generated from protobuf field <code>string service_name = 1;</code>
     * @return string
     */
    public function getServiceName()
    {
        return $this->service_name;
    }

    /**
     * XXX When we want to include more details about the sub-operation that was
     * executed against this service, we should include that here in each fetch node.
     * This might include an operation signature, requires directive, reference resolutions, etc.
     *
     * Generated from protobuf field <code>string service_name = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setServiceName($var)
    {
        GPBUtil::checkString($var, True);
        $this->service_name = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool trace_parsing_failed = 2;</code>
     * @return bool
     */
    public function getTraceParsingFailed()
    {
        return $this->trace_parsing_failed;
    }

    /**
     * Generated from protobuf field <code>bool trace_parsing_failed = 2;</code>
     * @param bool $var
     * @return $this
     */
    public function setTraceParsingFailed($var)
    {
        GPBUtil::checkBool($var);
        $this->trace_parsing_failed = $var;

        return $this;
    }

    /**
     * This Trace only contains start_time, end_time, duration_ns, and root;
     * all timings were calculated **on the federated service**, and clock skew
     * will be handled by the ingress server.
     *
     * Generated from protobuf field <code>.mdg.engine.proto.Trace trace = 3;</code>
     * @return \Mdg\Trace|null
     */
    public function getTrace()
    {
        return $this->trace;
    }

    public function hasTrace()
    {
        return isset($this->trace);
    }

    public function clearTrace()
    {
        unset($this->trace);
    }

    /**
     * This Trace only contains start_time, end_time, duration_ns, and root;
     * all timings were calculated **on the federated service**, and clock skew
     * will be handled by the ingress server.
     *
     * Generated from protobuf field <code>.mdg.engine.proto.Trace trace = 3;</code>
     * @param \Mdg\Trace $var
     * @return $this
     */
    public function setTrace($var)
    {
        GPBUtil::checkMessage($var, \Mdg\Trace::class);
        $this->trace = $var;

        return $this;
    }

    /**
     * relative to the outer trace's start_time, in ns, measured in the gateway.
     *
     * Generated from protobuf field <code>uint64 sent_time_offset = 4;</code>
     * @return int|string
     */
    public function getSentTimeOffset()
    {
        return $this->sent_time_offset;
    }

    /**
     * relative to the outer trace's start_time, in ns, measured in the gateway.
     *
     * Generated from protobuf field <code>uint64 sent_time_offset = 4;</code>
     * @param int|string $var
     * @return $this
     */
    public function setSentTimeOffset($var)
    {
        GPBUtil::checkUint64($var);
        $this->sent_time_offset = $var;

        return $this;
    }

    /**
     * Wallclock times measured in the gateway for when this operation was
     * sent and received.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp sent_time = 5;</code>
     * @return \Google\Protobuf\Timestamp|null
     */
    public function getSentTime()
    {
        return $this->sent_time;
    }

    public function hasSentTime()
    {
        return isset($this->sent_time);
    }

    public function clearSentTime()
    {
        unset($this->sent_time);
    }

    /**
     * Wallclock times measured in the gateway for when this operation was
     * sent and received.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp sent_time = 5;</code>
     * @param \Google\Protobuf\Timestamp $var
     * @return $this
     */
    public function setSentTime($var)
    {
        GPBUtil::checkMessage($var, \Google\Protobuf\Timestamp::class);
        $this->sent_time = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp received_time = 6;</code>
     * @return \Google\Protobuf\Timestamp|null
     */
    public function getReceivedTime()
    {
        return $this->received_time;
    }

    public function hasReceivedTime()
    {
        return isset($this->received_time);
    }

    public function clearReceivedTime()
    {
        unset($this->received_time);
    }

    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp received_time = 6;</code>
     * @param \Google\Protobuf\Timestamp $var
     * @return $this
     */
    public function setReceivedTime($var)
    {
        GPBUtil::checkMessage($var, \Google\Protobuf\Timestamp::class);
        $this->received_time = $var;

        return $this;
    }

}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(FetchNode::class, \Mdg\Trace_QueryPlanNode_FetchNode::class);

