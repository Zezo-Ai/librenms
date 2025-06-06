<?php

/**
 * Arubaos.php
 *
 * HPE ArubaOS
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\OS;

use App\Models\Device;
use LibreNMS\Device\WirelessSensor;
use LibreNMS\Interfaces\Discovery\OSDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessApCountDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessClientsDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessFrequencyDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessNoiseFloorDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessPowerDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessUtilizationDiscovery;
use LibreNMS\Interfaces\Polling\Sensors\WirelessFrequencyPolling;
use LibreNMS\OS;
use LibreNMS\Util\Number;
use SnmpQuery;

class Arubaos extends OS implements
    OSDiscovery,
    WirelessApCountDiscovery,
    WirelessClientsDiscovery,
    WirelessFrequencyDiscovery,
    WirelessFrequencyPolling,
    WirelessNoiseFloorDiscovery,
    WirelessPowerDiscovery,
    WirelessUtilizationDiscovery
{
    public function discoverOS(Device $device): void
    {
        parent::discoverOS($device); // yaml
        $aruba_info = SnmpQuery::get([
            'WLSX-SWITCH-MIB::wlsxSwitchRole.0',
            'WLSX-SWITCH-MIB::wlsxSwitchMasterIp.0',
            'WLSX-SWITCH-MIB::wlsxSwitchLicenseSerialNumber.0',
        ])->values();

        $device->features = ($aruba_info['WLSX-SWITCH-MIB::wlsxSwitchRole.0'] ?? null) == 'master' ? 'Master Controller' : 'Local Controller for ' . ($aruba_info['WLSX-SWITCH-MIB::wlsxSwitchMasterIp.0'] ?? null);
        $device->serial = $aruba_info['WLSX-SWITCH-MIB::wlsxSwitchLicenseSerialNumber.0'] ?? null;
    }

    /**
     * Discover wireless client counts. Type is clients.
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array Sensors
     */
    public function discoverWirelessClients()
    {
        $oid = '.1.3.6.1.4.1.14823.2.2.1.1.3.2.0'; // WLSX-SWITCH-MIB::wlsxSwitchTotalNumStationsAssociated.0

        return [
            new WirelessSensor('clients', $this->getDeviceId(), $oid, 'arubaos', 1, 'Client Count'),
        ];
    }

    /**
     * Discover wireless AP counts. Type is ap-count.
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array Sensors
     */
    public function discoverWirelessApCount(): array
    {
        $apCount = SnmpQuery::get('WLSX-SWITCH-MIB::wlsxSwitchTotalNumAccessPoints.0')->value();

        if (! is_numeric($apCount)) {
            return [];
        }

        $oid = '.1.3.6.1.4.1.14823.2.2.1.1.3.1.0';
        $apCount = intval($apCount);

        $low_warn_const = 1; // Default warning threshold = 1 down AP
        $low_limit_const = 10; // Default critical threshold = 10 down APs

        // Calculate default thresholds based on current AP count
        $low_warn = $apCount - $low_warn_const;
        $low_limit = $apCount - $low_limit_const;

        // For small current AP counts, set thresholds differently:
        // If AP count is less than twice the default critical threshold,
        // then set the critical threshold to roughly half the current AP count.
        if ($apCount < $low_limit_const * 2) {
            $low_limit = round($apCount / 2, 0, PHP_ROUND_HALF_DOWN);
        }
        // If AP count is less than the default warning hreshold,
        // then don't bother setting thresholds.
        if ($apCount <= $low_warn_const) {
            $low_warn = null;
            $low_limit = null;
        }

        // If AP count is less than twice the default warning threshold,
        // then set the critical threshold to zero.
        if ($apCount > 0 && $apCount <= $low_warn_const * 2) {
            $low_limit = 0;
        }

        return [
            new WirelessSensor('ap-count', $this->getDeviceId(), $oid, 'arubaos', 1, 'AP Count', $apCount, 1, 1, 'sum', null, null, $low_limit, null, $low_warn),
        ];
    }

    /**
     * Discover wireless frequency.  This is in MHz. Type is frequency.
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array Sensors
     */
    public function discoverWirelessFrequency()
    {
        // instant
        return $this->discoverInstantRadio('frequency', 'aiRadioChannel');
    }

    /**
     * Discover wireless noise floor. This is in dBm/Hz. Type is noise-floor.
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array
     */
    public function discoverWirelessNoiseFloor()
    {
        // instant
        return $this->discoverInstantRadio('noise-floor', 'aiRadioNoiseFloor');
    }

    /**
     * Discover wireless tx or rx power. This is in dBm. Type is power.
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array
     */
    public function discoverWirelessPower()
    {
        // instant
        return $this->discoverInstantRadio('power', 'aiRadioTransmitPower', 'Radio %s: Tx Power');
    }

    protected function decodeChannel($channel)
    {
        return Number::cast($channel) & 255; // mask off the channel width information
    }

    private function discoverInstantRadio($type, $oid, $desc = 'Radio %s')
    {
        $data = SnmpQuery::numeric()->walk("AI-AP-MIB::$oid")->groupByIndex(1); // group by radio index

        $sensors = [];
        foreach ($data as $index => $entry) {
            $value = reset($entry);
            $oid = key($entry);

            if ($type == 'frequency') {
                $value = WirelessSensor::channelToFrequency($this->decodeChannel($value));
            }

            $sensors[] = new WirelessSensor(
                $type,
                $this->getDeviceId(),
                $oid,
                'arubaos-iap',
                $oid,
                trim(sprintf($desc, $index)),
                $value
            );
        }

        return $sensors;
    }

    /**
     * Discover wireless utilization.  This is in %. Type is utilization.
     * Returns an array of LibreNMS\Device\Sensor objects that have been discovered
     *
     * @return array Sensors
     */
    public function discoverWirelessUtilization()
    {
        // instant
        return $this->discoverInstantRadio('utilization', 'aiRadioUtilization64');
    }

    /**
     * Poll wireless frequency as MHz
     * The returned array should be sensor_id => value pairs
     *
     * @param  array  $sensors  Array of sensors needed to be polled
     * @return array of polled data
     */
    public function pollWirelessFrequency(array $sensors)
    {
        return $this->pollWirelessChannelAsFrequency($sensors, [$this, 'decodeChannel']);
    }
}
