<?php

/**
 *	\file       dolibarr/custom/myo2xpmodule/core/boxes/box_factures_imp_o2xp.php
 *	\ingroup    factures
 *	\brief      Module de generation de l'affichage de la box factures impayees
 */

require_once DOL_DOCUMENT_ROOT . "/core/boxes/modules_boxes.php";
require_once DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php";


/**
 * Class to manage the box to show last invoices
 */
class box_factures_imp_o2xp extends ModeleBoxes
{
    public $boxcode="oldestunpaidcustomerbillso2xp";
    public $boximg="object_bill";
    public $boxlabel="BoxOldestUnpaidCustomerBills";
    public $depends = array("facture");

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

	    $this->hidden=! ($user->rights->facture->lire);
	}

	/**
	 *  Load data into info_box_contents array to show array later.
	 *
	 *  @param	int		$max        Maximum number of records to load
     *  @return	void
	 */
	public function loadBox($max = 5)
	{
		global $conf, $user, $langs, $db;

		$this->max=$max;

		include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

        $facturestatic = new Facture($db);
        $societestatic = new Societe($db);

		$this->info_box_head = array('text' => $langs->trans("BoxTitleOldestUnpaidCustomerBills", $max));

		if ($user->rights->facture->lire)
		{
			$sql = "SELECT s.nom as name, s.rowid as socid, s.email,";
            $sql.= " s.code_client,";
            $sql.= " s.logo,";
			$sql.= " f.ref, f.date_lim_reglement as datelimite,";
			$sql.= " f.type,";
			$sql.= " f.note_public, ref_client,";//P06605
			$sql.= " f.amount, f.datef as df,";
            $sql.= " f.total as total_ht,";
            $sql.= " f.tva as total_tva,";
            $sql.= " f.total_ttc,";
			$sql.= " f.paye, f.fk_statut, f.rowid as facid";
			$sql.= ", sum(pf.amount) as am";
			$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
			if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			$sql.= ", ".MAIN_DB_PREFIX."facture as f";
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON f.rowid=pf.fk_facture ";
			$sql.= " WHERE f.fk_soc = s.rowid";
			$sql.= " AND f.entity IN (".getEntity('invoice').")";
			$sql.= " AND f.paye = 0";
			$sql.= " AND fk_statut = 1";
			if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
			if($user->societe_id) $sql.= " AND s.rowid = ".$user->societe_id;
			$sql.= " GROUP BY s.nom, s.rowid, s.code_client, s.logo, f.ref, f.date_lim_reglement,";
			$sql.= " f.type, f.amount, f.datef, f.total, f.tva, f.total_ttc, f.paye, f.fk_statut, f.rowid";
			//$sql.= " ORDER BY f.datef DESC, f.ref DESC ";
			$sql.= " ORDER BY datelimite ASC, f.ref ASC ";
			$sql.= $db->plimit($max, 0);

			$result = $db->query($sql);
			if ($result)
			{
				$num = $db->num_rows($result);
				$now=dol_now();

				$line = 0;
				$l_due_date = $langs->trans('Late').' ('.strtolower($langs->trans('DateDue')).': %s)';

				while ($line < $num)
				{
					$objp = $db->fetch_object($result);
					$datelimite=$db->jdate($objp->datelimite);
                    $facturestatic->id = $objp->facid;
                    $facturestatic->ref = $objp->ref;
                    $facturestatic->type = $objp->type;
                    $facturestatic->total_ht = $objp->total_ht;
                    $facturestatic->total_tva = $objp->total_tva;
                    $facturestatic->total_ttc = $objp->total_ttc;
					$facturestatic->statut = $objp->fk_statut;
					$facturestatic->date_lim_reglement = $db->jdate($objp->datelimite);
                    $facturestatic->ref_client = $objp->ref_client;//P06605
                    $facturestatic->note_public = $objp->note_public;//P06605

                    $societestatic->id = $objp->socid;
                    $societestatic->name = $objp->name;
                    $societestatic->client = 1;
                    $societestatic->email = $objp->email;
                    $societestatic->code_client = $objp->code_client;
                    $societestatic->logo = $objp->logo;

					$late='';
					if ($facturestatic->hasDelay()) {
						$late = img_warning(sprintf($l_due_date, dol_print_date($datelimite, 'day')));
					}

                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $facturestatic->getNomUrl(1),
                        'text2'=> $late,
                        'asis' => 1,
                    );

                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $societestatic->getNomUrl(1, '', 44),
                        'asis' => 1,
                    );

                    //P06605
                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $facturestatic->ref_client,
                    );

                    //P06605
                    $this->info_box_contents[$line][] = array(
                        'td' => '',
                        'text' => $facturestatic->note_public,
                    );

                    $this->info_box_contents[$line][] = array(
                        'td' => 'class="nowrap right"',
                        'text' => price($objp->total_ht, 0, $langs, 0, -1, -1, $conf->currency),
                    );

                    $this->info_box_contents[$line][] = array(
                        'td' => 'class="right"',
                        'text' => dol_print_date($datelimite, 'day'),
                    );

                    $this->info_box_contents[$line][] = array(
                        'td' => 'class="right" width="18"',
                        'text' => $facturestatic->LibStatut($objp->paye, $objp->fk_statut, 3, $objp->am),
                    );

					$line++;
				}

				if ($num==0) $this->info_box_contents[$line][0] = array('td' => 'class="center"','text'=>$langs->trans("NoUnpaidCustomerBills"));

				$db->free($result);
			}
			else
			{
                $this->info_box_contents[0][0] = array(
                    'td' => '',
                    'maxlength'=>500,
                    'text' => ($db->error().' sql='.$sql),
                );
			}
		}
		else {
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
