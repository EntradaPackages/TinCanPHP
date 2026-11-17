<?php
/*
    Copyright 2014 Rustici Software

    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
*/

namespace TinCan;

use InvalidArgumentException;

class Statement extends StatementBase
{
    const SIGNATURE_USAGE_TYPE = 'http://adlnet.gov/expapi/attachments/signature';
    const SIGNATURE_CONTENT_TYPE = 'application/octet-stream';

    protected $id;

    //
    // stored *must* store a string because DateTime doesn't
    // support sub-second precision, the setter will take a DateTime and convert
    // it to the proper ISO8601 representation, but if a user needs sub-second
    // precision as afforded by the spec they will have to create their own,
    // they can see TinCan\Util::getTimestamp for an example of how to do so
    //
    protected $stored;

    protected $authority;
    protected $version;
    protected $attachments;

    public function __construct() {
        call_user_func_array('parent::__construct', func_get_args());

        if (func_num_args() == 1) {
            $arg = func_get_arg(0);

            //
            // 'object' isn't in the list of properties so ._fromArray doesn't
            // pick it up correctly, but 'target' and 'object' shouldn't be in
            // the args at the same time, so handle 'object' here
            //
            if (isset($arg['object'])) {
                $this->setObject($arg['object']);
            }
        }
        if (! isset($this->attachments)) {
            $this->setAttachments(array());
        }
    }

    public function stamp() {
        $this->setId(Util::getUUID());
        $this->setTimestamp(Util::getTimestamp());

        return $this;
    }

    public function compareWithSignature($fromSig) {
        foreach (array('id', 'attachments') as $property) {
            if (! isset($this->$property) && ! isset($fromSig->$property)) {
                continue;
            }
            if (isset($this->$property) && ! isset($fromSig->$property)) {
                return array('success' => false, 'reason' => "Comparison of $property failed: value not in signature");
            }
            if (isset($fromSig->$property) && ! isset($this->$property)) {
                return array('success' => false, 'reason' => "Comparison of $property failed: value not in this");
            }
        }
        if (isset($this->id)) {
            if ($this->id !== $fromSig->id) {
                return array('success' => false, 'reason' => 'Comparison of id failed: value is not the same');
            }
        }
        if (isset($this->attachments)) {
            if (count($this->attachments) !== count($fromSig->attachments)) {
                return array('success' => false, 'reason' => 'Comparison of attachments list failed: array lengths differ');
            }

            for ($i = 0; $i < count($this->attachments); $i++) {
                $comparison = $this->attachments[$i]->compareWithSignature($fromSig->attachments[$i]);
                if (! $comparison['success']) {
                    return array('success' => false, 'reason' => "Comparison of attachment $i failed: " . $comparison['reason']);
                }
            }
        }

        return parent::compareWithSignature($fromSig);
    }

    public function setId($value) {
        if (isset($value) && ! preg_match(Util::UUID_REGEX, $value)) {
            throw new \InvalidArgumentException('arg1 must be a UUID "' . $value . '"');
        }
        $this->id = $value;
        return $this;
    }
    public function getId() { return $this->id; }
    public function hasId() { return isset($this->id); }

    public function setStored($value) {
        if (isset($value)) {
            if ($value instanceof \DateTime) {
                // Use format('c') instead of format(\DateTime::ISO8601) due to bug in format(\DateTime::ISO8601) that generates an invalid timestamp.
                $value = $value->format('c');
            }
            elseif (is_string($value)) {
                $value = $value;
            }
            else {
                throw new \InvalidArgumentException('type of arg1 must be string or DateTime');
            }
        }

        $this->stored = $value;

        return $this;
    }
    public function getStored() { return $this->stored; }

    public function setAuthority($value) {
        if (! $value instanceof Agent && is_array($value)) {
            $value = new Agent($value);
        }

        $this->authority = $value;

        return $this;
    }
    public function getAuthority() { return $this->authority; }

    public function setVersion($value) { $this->version = $value; return $this; }
    public function getVersion() { return $this->version; }

    public function setAttachments($value) {
        foreach ($value as $k => $v) {
            if (! $value[$k] instanceof Attachment) {
                $value[$k] = new Attachment($value[$k]);
            }
        }

        $this->attachments = $value;

        return $this;
    }
    public function getAttachments() { return $this->attachments; }
    public function hasAttachments() { return count($this->attachments) > 0; }
    public function hasAttachmentsWithContent() {
        if (! $this->hasAttachments()) {
            return false;
        }

        foreach ($this->attachments as $attachment) {
            if ($attachment->hasContent()) {
                return true;
            }
        }

        return false;
    }
    public function addAttachment($value) {
        if (! $value instanceof Attachment) {
            $value = new Attachment($value);
        }

        array_push($this->attachments, $value);

        return $this;
    }
}
