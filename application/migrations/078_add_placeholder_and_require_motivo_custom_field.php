<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Adds generic placeholder support to ea_custom_fields, then uses it to make the
 * "Motivo de servicio" field (id 10) mandatory with a helpful hint.
 */
class Migration_Add_placeholder_and_require_motivo_custom_field extends EA_Migration
{
    private const FIELD_ID = 10;
    private const PLACEHOLDER = 'Describa la falla o el error de su equipo. Ej.: no enciende, no carga la batería, muestra un código de error o hace ruido al avanzar.';

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('custom_fields')) {
            return;
        }

        if (!$this->db->field_exists('placeholder', 'custom_fields')) {
            $this->dbforge->add_column('custom_fields', [
                'placeholder' => [
                    'type' => 'VARCHAR',
                    'constraint' => '500',
                    'null' => true,
                    'after' => 'label',
                ],
            ]);
        }

        $this->db
            ->where('id', self::FIELD_ID)
            ->update('custom_fields', [
                'required' => 1,
                'placeholder' => self::PLACEHOLDER,
            ]);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('custom_fields')) {
            return;
        }

        $this->db
            ->where('id', self::FIELD_ID)
            ->update('custom_fields', [
                'required' => 0,
                'placeholder' => null,
            ]);

        if ($this->db->field_exists('placeholder', 'custom_fields')) {
            $this->dbforge->drop_column('custom_fields', 'placeholder');
        }
    }
}
