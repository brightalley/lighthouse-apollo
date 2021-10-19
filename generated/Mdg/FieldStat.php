<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: reports.proto

namespace Mdg;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>mdg.engine.proto.FieldStat</code>
 */
class FieldStat extends \Google\Protobuf\Internal\Message
{
    /**
     * required; eg "String!" for User.email:String!
     *
     * Generated from protobuf field <code>string return_type = 3;</code>
     */
    protected $return_type = '';
    /**
     * Generated from protobuf field <code>uint64 errors_count = 4;</code>
     */
    protected $errors_count = 0;
    /**
     * Generated from protobuf field <code>uint64 count = 5;</code>
     */
    protected $count = 0;
    /**
     * Generated from protobuf field <code>uint64 requests_with_errors_count = 6;</code>
     */
    protected $requests_with_errors_count = 0;
    /**
     * Duration histogram; see docs/histograms.md
     *
     * Generated from protobuf field <code>repeated sint64 latency_count = 9;</code>
     */
    private $latency_count;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $return_type
     *           required; eg "String!" for User.email:String!
     *     @type int|string $errors_count
     *     @type int|string $count
     *     @type int|string $requests_with_errors_count
     *     @type int[]|string[]|\Google\Protobuf\Internal\RepeatedField $latency_count
     *           Duration histogram; see docs/histograms.md
     * }
     */
    public function __construct($data = NULL) {
        \Metadata\Reports::initOnce();
        parent::__construct($data);
    }

    /**
     * required; eg "String!" for User.email:String!
     *
     * Generated from protobuf field <code>string return_type = 3;</code>
     * @return string
     */
    public function getReturnType()
    {
        return $this->return_type;
    }

    /**
     * required; eg "String!" for User.email:String!
     *
     * Generated from protobuf field <code>string return_type = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setReturnType($var)
    {
        GPBUtil::checkString($var, True);
        $this->return_type = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 errors_count = 4;</code>
     * @return int|string
     */
    public function getErrorsCount()
    {
        return $this->errors_count;
    }

    /**
     * Generated from protobuf field <code>uint64 errors_count = 4;</code>
     * @param int|string $var
     * @return $this
     */
    public function setErrorsCount($var)
    {
        GPBUtil::checkUint64($var);
        $this->errors_count = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 count = 5;</code>
     * @return int|string
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Generated from protobuf field <code>uint64 count = 5;</code>
     * @param int|string $var
     * @return $this
     */
    public function setCount($var)
    {
        GPBUtil::checkUint64($var);
        $this->count = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 requests_with_errors_count = 6;</code>
     * @return int|string
     */
    public function getRequestsWithErrorsCount()
    {
        return $this->requests_with_errors_count;
    }

    /**
     * Generated from protobuf field <code>uint64 requests_with_errors_count = 6;</code>
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
     * Duration histogram; see docs/histograms.md
     *
     * Generated from protobuf field <code>repeated sint64 latency_count = 9;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getLatencyCount()
    {
        return $this->latency_count;
    }

    /**
     * Duration histogram; see docs/histograms.md
     *
     * Generated from protobuf field <code>repeated sint64 latency_count = 9;</code>
     * @param int[]|string[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setLatencyCount($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::SINT64);
        $this->latency_count = $arr;

        return $this;
    }

}

