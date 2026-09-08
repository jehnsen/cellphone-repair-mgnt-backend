<?php

namespace Database\Seeders;

use App\Models\DevicePart;
use App\Models\IssuePartLink;
use App\Support\Diagnosis\IssueSource;
use App\Support\Diagnosis\PartCategory;
use App\Support\Diagnosis\ProblemTag;
use App\Support\RepairFinding\Defect;
use Illuminate\Database\Seeder;

/**
 * The generic phone rig, and what each issue in the shop's two vocabularies
 * points at.
 *
 * One rig for every handset that walks in, not one per SKU. Geometry is
 * millimetres against a nominal 150 x 72 x 8 mm slab, origin at the centre,
 * +x right, +y up the screen, +z out of the display face. Nothing here claims
 * to be a particular phone — it claims that the battery is the big flat thing
 * in the middle and the charging port is at the bottom edge, which is true of
 * every phone the shop has ever opened, and is the whole of what a customer
 * needs to follow along.
 *
 * Idempotent: keyed upserts, so running it again corrects geometry rather
 * than duplicating the rig. Mappings for an issue are replaced wholesale for
 * the same reason.
 */
class DevicePartSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->parts() as $sortOrder => $part) {
            DevicePart::updateOrCreate(
                ['key' => $part['key']],
                $part + ['sort_order' => $sortOrder * 10, 'is_active' => true],
            );
        }

        $partIds = DevicePart::query()->pluck('id', 'key');

        foreach ($this->mapping() as $source => $issues) {
            foreach ($issues as $issueKey => $partKeys) {
                IssuePartLink::query()
                    ->where('issue_source', $source)
                    ->where('issue_key', $issueKey)
                    ->delete();

                foreach (array_values($partKeys) as $index => $partKey) {
                    IssuePartLink::create([
                        'issue_source' => $source,
                        'issue_key' => $issueKey,
                        'device_part_id' => $partIds[$partKey],
                        'rank' => $index + 1,
                    ]);
                }
            }
        }
    }

    /**
     * Front of the device first, which is also the order it comes apart in.
     *
     * The internals sit *inside* the mid-frame's own volume on purpose: with
     * everything assembled you see glass, frame and back cover and nothing
     * else, exactly like a real phone. Diagnosis mode drops the rest to a low
     * opacity so the implicated part shows through; explore mode pulls them
     * apart along `explode`.
     *
     * @return list<array<string, mixed>>
     */
    private function parts(): array
    {
        return [
            [
                'key' => 'front_glass',
                'label' => 'Front glass / digitizer',
                'category' => PartCategory::Display->value,
                'blurb' => 'The outer glass you touch. It carries the touch layer, so a crack here can stop touch working even when the picture underneath is perfect.',
                'pos_x' => 0, 'pos_y' => 0, 'pos_z' => 3.6,
                'size_x' => 71, 'size_y' => 149, 'size_z' => 0.8,
                'explode_x' => 0, 'explode_y' => 0, 'explode_z' => 1, 'explode_distance' => 46,
            ],
            [
                'key' => 'display_assembly',
                'label' => 'Display panel',
                'category' => PartCategory::Display->value,
                'blurb' => 'The screen itself — what actually makes the picture. Black patches, lines, or dead colour are this, not the glass.',
                'pos_x' => 0, 'pos_y' => 0, 'pos_z' => 2.6,
                'size_x' => 69, 'size_y' => 145, 'size_z' => 1.2,
                'explode_x' => 0, 'explode_y' => 0, 'explode_z' => 1, 'explode_distance' => 32,
            ],
            [
                'key' => 'earpiece_speaker',
                'label' => 'Earpiece speaker',
                'category' => PartCategory::Audio->value,
                'blurb' => 'The small speaker at the top you hold to your ear on a call.',
                'pos_x' => 0, 'pos_y' => 70, 'pos_z' => 1.6,
                'size_x' => 15, 'size_y' => 4, 'size_z' => 2.4,
                'explode_x' => 0, 'explode_y' => 1, 'explode_z' => 0.4, 'explode_distance' => 34,
            ],
            [
                'key' => 'front_camera',
                'label' => 'Front camera',
                'category' => PartCategory::Camera->value,
                'blurb' => 'The selfie camera, just under the glass at the top.',
                'pos_x' => 16, 'pos_y' => 66, 'pos_z' => 1.4,
                'size_x' => 6, 'size_y' => 6, 'size_z' => 3,
                'explode_x' => 0.5, 'explode_y' => 1, 'explode_z' => 0.3, 'explode_distance' => 30,
            ],
            [
                'key' => 'mid_frame',
                'label' => 'Mid-frame / chassis',
                'category' => PartCategory::Structural->value,
                'blurb' => 'The metal skeleton everything else bolts to. A bad drop bends this, and then nothing else sits flat.',
                'pos_x' => 0, 'pos_y' => 0, 'pos_z' => 0,
                'size_x' => 72, 'size_y' => 150, 'size_z' => 5.5,
                'explode_x' => 0, 'explode_y' => 0, 'explode_z' => 1, 'explode_distance' => 8,
            ],
            [
                'key' => 'logic_board',
                'label' => 'Logic board',
                'category' => PartCategory::Board->value,
                'blurb' => 'The main circuit board — processor, memory, and the power circuitry. Board-level work is the slowest and most expensive kind.',
                'pos_x' => 0, 'pos_y' => 46, 'pos_z' => -0.6,
                'size_x' => 64, 'size_y' => 44, 'size_z' => 1.6,
                'explode_x' => 0, 'explode_y' => 0.35, 'explode_z' => -1, 'explode_distance' => 26,
            ],
            [
                'key' => 'battery',
                'label' => 'Battery',
                'category' => PartCategory::Power->value,
                'blurb' => 'The big flat cell taking up most of the middle. Batteries wear out with age and charge cycles — this is normal, not damage.',
                'pos_x' => 0, 'pos_y' => -18, 'pos_z' => -0.7,
                'size_x' => 58, 'size_y' => 76, 'size_z' => 3.4,
                'explode_x' => 0, 'explode_y' => -0.25, 'explode_z' => -1, 'explode_distance' => 34,
            ],
            [
                'key' => 'rear_camera_main',
                'label' => 'Rear camera module',
                'category' => PartCategory::Camera->value,
                'blurb' => 'The main camera cluster on the back. Focus problems are usually the module itself rather than the glass over it.',
                'pos_x' => -20, 'pos_y' => 54, 'pos_z' => -1.6,
                'size_x' => 24, 'size_y' => 30, 'size_z' => 4.2,
                'explode_x' => -0.5, 'explode_y' => 0.4, 'explode_z' => -1, 'explode_distance' => 38,
            ],
            [
                'key' => 'charging_port_flex',
                'label' => 'Charging port flex',
                'category' => PartCategory::Connectivity->value,
                'blurb' => 'The socket you plug the charger into, on a flat ribbon cable. The main microphone usually rides on this same part.',
                'pos_x' => 0, 'pos_y' => -66, 'pos_z' => -0.8,
                'size_x' => 32, 'size_y' => 14, 'size_z' => 2.2,
                'explode_x' => 0, 'explode_y' => -1, 'explode_z' => -0.3, 'explode_distance' => 30,
            ],
            [
                'key' => 'loud_speaker',
                'label' => 'Loudspeaker',
                'category' => PartCategory::Audio->value,
                'blurb' => 'The speaker at the bottom edge — ringtones, music, speakerphone.',
                'pos_x' => 21, 'pos_y' => -68, 'pos_z' => -1.4,
                'size_x' => 18, 'size_y' => 11, 'size_z' => 3,
                'explode_x' => 0.5, 'explode_y' => -1, 'explode_z' => -0.4, 'explode_distance' => 36,
            ],
            [
                'key' => 'vibration_motor',
                'label' => 'Vibration motor',
                'category' => PartCategory::Audio->value,
                'blurb' => 'The small weighted motor that makes the phone buzz.',
                'pos_x' => -24, 'pos_y' => -56, 'pos_z' => -1.5,
                'size_x' => 11, 'size_y' => 11, 'size_z' => 2.6,
                'explode_x' => -0.6, 'explode_y' => -0.7, 'explode_z' => -1, 'explode_distance' => 28,
            ],
            [
                'key' => 'power_button',
                'label' => 'Power button',
                'category' => PartCategory::Power->value,
                'blurb' => 'The side button that wakes and sleeps the phone.',
                'pos_x' => 36, 'pos_y' => 30, 'pos_z' => 0,
                'size_x' => 2.4, 'size_y' => 18, 'size_z' => 2.4,
                'explode_x' => 1, 'explode_y' => 0, 'explode_z' => 0, 'explode_distance' => 32,
            ],
            [
                'key' => 'volume_flex',
                'label' => 'Volume buttons',
                'category' => PartCategory::Connectivity->value,
                'blurb' => 'The volume rocker and its ribbon cable, down the opposite edge.',
                'pos_x' => -36, 'pos_y' => 34, 'pos_z' => 0,
                'size_x' => 2.4, 'size_y' => 34, 'size_z' => 2.4,
                'explode_x' => -1, 'explode_y' => 0, 'explode_z' => 0, 'explode_distance' => 32,
            ],
            [
                'key' => 'sim_tray',
                'label' => 'SIM tray',
                'category' => PartCategory::Connectivity->value,
                'blurb' => 'The little pull-out drawer the SIM card sits in — and, on most phones, the memory card too.',
                'pos_x' => -36, 'pos_y' => -12, 'pos_z' => 0,
                'size_x' => 3, 'size_y' => 15, 'size_z' => 2.6,
                'explode_x' => -1, 'explode_y' => 0, 'explode_z' => 0, 'explode_distance' => 26,
            ],
            [
                'key' => 'back_glass',
                'label' => 'Back cover',
                'category' => PartCategory::Structural->value,
                'blurb' => 'The panel on the back. Cosmetic on most phones, but it has to come off before anything inside can be reached.',
                'pos_x' => 0, 'pos_y' => 0, 'pos_z' => -3.6,
                'size_x' => 72, 'size_y' => 150, 'size_z' => 0.9,
                'explode_x' => 0, 'explode_y' => 0, 'explode_z' => -1, 'explode_distance' => 48,
            ],
        ];
    }

    /**
     * Which parts an issue implicates, first one being the one the technician
     * means. Where an honest answer is "one of these three", it says three —
     * "no power" really is the battery, the board, or the button, and naming
     * one confidently would be a guess dressed up as a diagnosis.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function mapping(): array
    {
        return [
            // What the customer said at the counter.
            IssueSource::ProblemTag->value => [
                ProblemTag::Screen->value => ['front_glass', 'display_assembly'],
                ProblemTag::Battery->value => ['battery'],
                ProblemTag::ChargingPort->value => ['charging_port_flex'],
                ProblemTag::WaterDamage->value => ['logic_board', 'battery', 'charging_port_flex'],
                ProblemTag::NoPower->value => ['battery', 'logic_board', 'power_button'],
                ProblemTag::Software->value => ['logic_board'],
                ProblemTag::Camera->value => ['rear_camera_main', 'front_camera'],
                ProblemTag::Speaker->value => ['loud_speaker', 'earpiece_speaker'],
                ProblemTag::BoardLevel->value => ['logic_board'],
            ],

            // What the technician found once it was open. Note this is the
            // sharper of the two — 'screen' here leads with the panel, where
            // the customer's 'screen' leads with the glass, because by this
            // point somebody has actually looked.
            IssueSource::Defect->value => [
                Defect::Screen->value => ['display_assembly', 'front_glass'],
                Defect::Digitizer->value => ['front_glass'],
                Defect::Battery->value => ['battery'],
                Defect::ChargingPort->value => ['charging_port_flex'],
                Defect::Motherboard->value => ['logic_board'],
                Defect::PowerIc->value => ['logic_board'],
                Defect::CameraRear->value => ['rear_camera_main'],
                Defect::CameraFront->value => ['front_camera'],
                Defect::Speaker->value => ['loud_speaker'],
                Defect::Earpiece->value => ['earpiece_speaker'],
                // The main microphone rides on the charging port flex on most
                // handsets, which is why a bad mic and a bad port so often
                // turn out to be one part.
                Defect::Microphone->value => ['charging_port_flex'],
                Defect::Buttons->value => ['power_button', 'volume_flex'],
                Defect::BackCover->value => ['back_glass'],
                Defect::Housing->value => ['mid_frame'],
                Defect::SimReader->value => ['sim_tray'],
                Defect::SdReader->value => ['sim_tray'],
                Defect::WifiAntenna->value => ['logic_board', 'mid_frame'],
                // 'other' maps to nothing on purpose: the technician's note is
                // the diagnosis, and highlighting an arbitrary part would be
                // worse than highlighting none.
            ],
        ];
    }
}
