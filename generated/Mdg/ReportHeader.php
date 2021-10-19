<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: reports.proto

namespace Mdg;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * The `service` value embedded within the header key is not guaranteed to contain an actual service,
 * and, in most cases, the service information is trusted to come from upstream processing. If the
 * service _is_ specified in this header, then it is checked to match the context that is reporting it.
 * Otherwise, the service information is deduced from the token context of the reporter and then sent
 * along via other mechanisms (in Kafka, the `ReportKafkaKey). The other information (hostname,
 * agent_version, etc.) is sent by the Apollo Engine Reporting agent, but we do not currently save that
 * information to any of our persistent storage.
 *
 * Generated from protobuf message <code>mdg.engine.proto.ReportHeader</code>
 */
class ReportHeader extends \Google\Protobuf\Internal\Message
{
    /**
     * eg "mygraph&#64;myvariant"
     *
     * Generated from protobuf field <code>string graph_ref = 12;</code>
     */
    protected $graph_ref = '';
    /**
     * eg "host-01.example.com"
     *
     * Generated from protobuf field <code>string hostname = 5;</code>
     */
    protected $hostname = '';
    /**
     * eg "engineproxy 0.1.0"
     *
     * Generated from protobuf field <code>string agent_version = 6;</code>
     */
    protected $agent_version = '';
    /**
     * eg "prod-4279-20160804T065423Z-5-g3cf0aa8" (taken from `git describe --tags`)
     *
     * Generated from protobuf field <code>string service_version = 7;</code>
     */
    protected $service_version = '';
    /**
     * eg "node v4.6.0"
     *
     * Generated from protobuf field <code>string runtime_version = 8;</code>
     */
    protected $runtime_version = '';
    /**
     * eg "Linux box 4.6.5-1-ec2 #1 SMP Mon Aug 1 02:31:38 PDT 2016 x86_64 GNU/Linux"
     *
     * Generated from protobuf field <code>string uname = 9;</code>
     */
    protected $uname = '';
    /**
     * An id that is used to represent the schema to Apollo Graph Manager
     * Using this in place of what used to be schema_hash, since that is no longer
     * attached to a schema in the backend.
     *
     * Generated from protobuf field <code>string executable_schema_id = 11;</code>
     */
    protected $executable_schema_id = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $graph_ref
     *           eg "mygraph&#64;myvariant"
     *     @type string $hostname
     *           eg "host-01.example.com"
     *     @type string $agent_version
     *           eg "engineproxy 0.1.0"
     *     @type string $service_version
     *           eg "prod-4279-20160804T065423Z-5-g3cf0aa8" (taken from `git describe --tags`)
     *     @type string $runtime_version
     *           eg "node v4.6.0"
     *     @type string $uname
     *           eg "Linux box 4.6.5-1-ec2 #1 SMP Mon Aug 1 02:31:38 PDT 2016 x86_64 GNU/Linux"
     *     @type string $executable_schema_id
     *           An id that is used to represent the schema to Apollo Graph Manager
     *           Using this in place of what used to be schema_hash, since that is no longer
     *           attached to a schema in the backend.
     * }
     */
    public function __construct($data = NULL) {
        \Metadata\Reports::initOnce();
        parent::__construct($data);
    }

    /**
     * eg "mygraph&#64;myvariant"
     *
     * Generated from protobuf field <code>string graph_ref = 12;</code>
     * @return string
     */
    public function getGraphRef()
    {
        return $this->graph_ref;
    }

    /**
     * eg "mygraph&#64;myvariant"
     *
     * Generated from protobuf field <code>string graph_ref = 12;</code>
     * @param string $var
     * @return $this
     */
    public function setGraphRef($var)
    {
        GPBUtil::checkString($var, True);
        $this->graph_ref = $var;

        return $this;
    }

    /**
     * eg "host-01.example.com"
     *
     * Generated from protobuf field <code>string hostname = 5;</code>
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * eg "host-01.example.com"
     *
     * Generated from protobuf field <code>string hostname = 5;</code>
     * @param string $var
     * @return $this
     */
    public function setHostname($var)
    {
        GPBUtil::checkString($var, True);
        $this->hostname = $var;

        return $this;
    }

    /**
     * eg "engineproxy 0.1.0"
     *
     * Generated from protobuf field <code>string agent_version = 6;</code>
     * @return string
     */
    public function getAgentVersion()
    {
        return $this->agent_version;
    }

    /**
     * eg "engineproxy 0.1.0"
     *
     * Generated from protobuf field <code>string agent_version = 6;</code>
     * @param string $var
     * @return $this
     */
    public function setAgentVersion($var)
    {
        GPBUtil::checkString($var, True);
        $this->agent_version = $var;

        return $this;
    }

    /**
     * eg "prod-4279-20160804T065423Z-5-g3cf0aa8" (taken from `git describe --tags`)
     *
     * Generated from protobuf field <code>string service_version = 7;</code>
     * @return string
     */
    public function getServiceVersion()
    {
        return $this->service_version;
    }

    /**
     * eg "prod-4279-20160804T065423Z-5-g3cf0aa8" (taken from `git describe --tags`)
     *
     * Generated from protobuf field <code>string service_version = 7;</code>
     * @param string $var
     * @return $this
     */
    public function setServiceVersion($var)
    {
        GPBUtil::checkString($var, True);
        $this->service_version = $var;

        return $this;
    }

    /**
     * eg "node v4.6.0"
     *
     * Generated from protobuf field <code>string runtime_version = 8;</code>
     * @return string
     */
    public function getRuntimeVersion()
    {
        return $this->runtime_version;
    }

    /**
     * eg "node v4.6.0"
     *
     * Generated from protobuf field <code>string runtime_version = 8;</code>
     * @param string $var
     * @return $this
     */
    public function setRuntimeVersion($var)
    {
        GPBUtil::checkString($var, True);
        $this->runtime_version = $var;

        return $this;
    }

    /**
     * eg "Linux box 4.6.5-1-ec2 #1 SMP Mon Aug 1 02:31:38 PDT 2016 x86_64 GNU/Linux"
     *
     * Generated from protobuf field <code>string uname = 9;</code>
     * @return string
     */
    public function getUname()
    {
        return $this->uname;
    }

    /**
     * eg "Linux box 4.6.5-1-ec2 #1 SMP Mon Aug 1 02:31:38 PDT 2016 x86_64 GNU/Linux"
     *
     * Generated from protobuf field <code>string uname = 9;</code>
     * @param string $var
     * @return $this
     */
    public function setUname($var)
    {
        GPBUtil::checkString($var, True);
        $this->uname = $var;

        return $this;
    }

    /**
     * An id that is used to represent the schema to Apollo Graph Manager
     * Using this in place of what used to be schema_hash, since that is no longer
     * attached to a schema in the backend.
     *
     * Generated from protobuf field <code>string executable_schema_id = 11;</code>
     * @return string
     */
    public function getExecutableSchemaId()
    {
        return $this->executable_schema_id;
    }

    /**
     * An id that is used to represent the schema to Apollo Graph Manager
     * Using this in place of what used to be schema_hash, since that is no longer
     * attached to a schema in the backend.
     *
     * Generated from protobuf field <code>string executable_schema_id = 11;</code>
     * @param string $var
     * @return $this
     */
    public function setExecutableSchemaId($var)
    {
        GPBUtil::checkString($var, True);
        $this->executable_schema_id = $var;

        return $this;
    }

}

