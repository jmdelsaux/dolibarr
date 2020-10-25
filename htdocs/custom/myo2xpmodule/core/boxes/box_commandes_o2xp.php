<?php

/**
 *		\file       dolibarr/custom/myo2xpmodule/core/boxes/box_commandes_o2xp.php
 *		\ingroup    commande
 *		\brief      Module de generation de l'affichage de la box commandes
 */

include_once DOL_DOCUMENT_ROOT . "/core/boxes/modules_boxes.php";


/**
 * Class to manage the box to show last orders
 */
class box_commandes_o2xp extends ModeleBoxes
{
    public $boxcode="lastcustomerorderso2xp";
    public $boximg="object_order";
    public $boxlabel="BoxLastCustomerOrders";
    public $depends = array("commande");

	/**
     * @var DoliDB Database handler.
     */
    public $db;
    public $param;
    public $info_box_head = array();
    public $info_box_contents = array();

    /**
     *  Constructor
     *
     *  @param  DoliDB  $db         Database handler
     *  @param  string  $param      More parameters
     */
    public function __construct(DoliDB $db, $param = '')
    {
        global $user;

        $this->db=$db;

        $this->hidden=! ($user->rights->commande->lire);
    }

    /**
     *  Load data for box to show them later
     *
     *  @param	int		$max        Maximum number of records to load
     *  @return	void
     */
    public function loadBox($max = 5)
    {
        global $user, $langs, $db, $conf;

        $this->max = $max;

        include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
        include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

        $commandestatic = new Commande($db);
        $societestatic = new Societe($db);
        $userstatic = new User($db);

        $this->info_box_head = array('text' => $langs->trans("BoxTitleLast".($conf->global->MAIN_LASTBOX_ON_OBJECT_DATE?"":"Modified")."CustomerOrders", $max));

        if ($user->rights->commande->lire)
        {
            $sql = "SELECT s.nom as name";
            $sql.= ", s.rowid as socid";
            $sql.= ", s.code_client";
            $sql.= ", s.logo, s.email";
			$sql.= ", c.note_public";//P06605
            $sql.= ", c.ref, c.tms";
            $sql.= ", c.rowid";
            $sql.= ", c.date_commande";
            $sql.= ", c.ref_client";
            $sql.= ", c.fk_statut";
            $sql.= ", c.fk_user_valid";
            $sql.= ", c.facture";
            $sql.= ", c.total_ht";
            $sql.= ", c.tva as total_tva";
            $sql.= ", c.total_ttc";
            $sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
            $sql.= ", ".MAIN_DB_PREFIX."commande as c";
            if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
            $sql.= " WHERE c.fk_soc = s.rowid";
            $sql.= " AND c.entity = ".$conf->entity;
            if (! empty($conf->global->ORDER_BOX_LAST_ORDERS_VALIDATED_ONLY)) $sql.=" AND c.fk_statut = 1";
            if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
            if ($user->societe_id) $sql.= " AND s.rowid = ".$user->societe_id;
            if ($conf->global->MAIN_LASTBOX_ON_OBJECT_DATE) $sql.= " ORDER BY c.date_commande DESC, c.ref DESC ";
            else $sql.= " ORDER BY c.tms DESC, c.ref DESC ";
            $sql.= $db->plimit($max, 0);

            $result = $db->query($sql);
            if ($result) {
                $num = $db->num_rows($result);

                $line = 0;

                while ($line < $num) {
                    $objp = $db->fetch_object($result);
                    $date=$db->jdate($objp->date_commande);
                    $datem=$db->jdate($objp->tms);
                    $commandestatic->id = $objp->rowid;
                    $commandestatic->ref = $objp->ref;
                    $commandestatic->ref_client = $objp->ref_client;
                    $commandestatic->total_ht = $objp->total_ht;
                    $commandestatic->total_tva = $objp->total_tva;
                    $commandestatic->total_ttc = $objp->total_ttc;
                    $commandestatic->note_public = $objp->note_public;//P06605
                    $societestatic->id = $objp->socid;
                    $societestatic->name = $objp->name;
                    $societestatic->email = $objp->email;
                    $societestatic->code_client = $objp->code_client;
                    $societestatic->logo = $objp->logo;

                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $commandestatic->getNomUrl(1),
                        'asis' => 1,
                    );

                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $societestatic->getNomUrl(1),
                        'asis' => 1,
                    );

                    //P06605
                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $commandestatic->ref_client,
                    );

                    //P06605
                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $commandestatic->note_public,
                    );

                    $this->info_box_contents[$line][] = array(
                        'td' => 'class="nowrap right"',
                        'text' => price($objp->total_ht, 0, $langs, 0, -1, -1, $conf->currency),
                    );

                    if (! empty($conf->global->ORDER_BOX_LAST_ORDERS_SHOW_VALIDATE_USER)) {
                        if ($objp->fk_user_valid > 0) $userstatic->fetch($objp->fk_user_valid);
                        $this->info_box_contents[$line][] = array(
                            'td' => 'class="right"',
                            'text' => (($objp->fk_user_valid > 0)?$userstatic->getNomUrl(1):''),
                            'asis' => 1,
                        );
                    }

                    $this->info_box_contents[$line][] = array(
                        'td' => 'class="right"',
                        'text' => dol_print_date($date, 'day'),
                    );

                    $this->info_box_contents[$line][] = array(
                        'td' => 'class="right" width="18"',
                        'text' => $commandestatic->LibStatut($objp->fk_statut, $objp->facture, 3),
                    );

                    $line++;
                }

                if ($num==0) $this->info_box_contents[$line][0] = array('td' => 'class="center"','text'=>$langs->trans("NoRecordedOrders"));

                $db->free($result);
            } else {
                $this->info_box_contents[0][0] = array(
                    'td' => '',
                    'maxlength'=>500,
                    'text' => ($db->error().' sql='.$sql),
                );
            }
        } else {
            $this->info_box_contents[0][0] = array(
                'td' => 'class="nohover opacitymedium left"',
                'text' => $langs->trans("ReadPermissionNotAllowed")
            );
        }
    }

	/**
	 *	Method to show box
	 *
	 *	@param	array	$head       Array with properties of box title
	 *	@param  array	$contents   Array with properties of box lines
	 *  @param	int		$nooutput	No print, only return string
	 *	@return	string
	 */
    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
