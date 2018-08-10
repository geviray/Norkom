<html> 
    <head>
        <meta charset="UTF-8">
        <title>Claim Aging</title>
        <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
    </head>
    <body>
<?php

include "include/db_config.php";
//ini_set ("display_errors", "1");
//select for Current Day
$OraDB = oci_connect("cpi", "cpi12345!", "10.18.0.218/PCIC");

$select1 = oci_parse($OraDB,"SELECT TRIM(TO_CHAR(SYSDATE, 'DAY'))DAY_NOW FROM DUAL");
oci_execute($select1);
$row = oci_fetch_assoc($select1);
$day = $row['DAY_NOW'];
$branch = $_GET["branch"];
$line   = $_GET["line"];

//select for messageto_char(loss_date,'MM-DD-YYYY')
$result = oci_parse($OraDB,"SELECT a.claim_id,get_claim_number (a.claim_id) claim, to_char(loss_date,'MM-DD-YYYY')loss_date, 
                                    assured_name,TRIM( TO_CHAR(SUM (disbursement_amt),'999,999,999.99')) payt_amt, get_ref_no (MAX (c.tran_id)) reference_payt,
                                    MAX (tran_date) tran_date, DECODE (MAX (e.check_release_date), NULL, 'N', 'Y') released_check, to_char(MAX (check_date),'MM-DD-YYYY')CHECK_DATE,
                                    calculate_business_days (MAX (check_date), SYSDATE) aging, replenished_tag, in_hou_adj, clm_stat_desc,
                                    TRIM( TO_CHAR(SUM(NVL(LOSS_RESERVE,0)*j.CONVERT_RATE),'999,999,999.99'))LOSS_RESERVE
                             FROM gicl_claims a, giac_direct_claim_payts b, giac_acctrans c, giac_chk_disbursement d,
                                  giac_chk_release_info e,giac_disb_vouchers f, giis_users g,giis_user_grp_hdr h, giis_clm_stat i,
                                  GICL_CLM_RES_HIST j
                             WHERE a.claim_id = b.claim_id
                                   AND b.gacc_tran_id = c.tran_id
                                   AND c.tran_flag IN ('C', 'P')
                                   and a.claim_id = j.claim_id(+)
                                   AND b.gacc_tran_id = d.gacc_tran_id(+)
                                   AND b.gacc_tran_id = e.gacc_tran_id(+)
                                   AND b.gacc_tran_id = f.gacc_tran_id(+)
                                   AND a.in_hou_adj = g.user_id
                                   AND g.user_grp = h.user_grp
                                   AND h.grp_iss_cd  = NVL('$branch',h.grp_iss_cd)
                                   AND a.line_cd = NVL('$line',a.line_cd)
                                   AND a.clm_stat_cd = i.clm_stat_cd
                                   AND a.clm_stat_cd NOT IN ('CD', 'CC', 'WD', 'DN')
                             GROUP BY get_claim_number (a.claim_id), loss_date, assured_name,
                                      replenished_tag, in_hou_adj,clm_stat_desc,a.claim_id
                             HAVING calculate_business_days (MAX (check_date), SYSDATE) >= 7                        
                             ORDER BY AGING DESC");

echo "<font size=3><b><br>List of Paid Claim/s Still Open per System </b></font><br>";
date_default_timezone_set("Asia/Hong_Kong");
echo date("l\, F jS\, Y ", time());

echo "<br>";
echo "<br>";
echo " <table class='table table-bordered' border = 1 width=100% align=center cellpadding=0 cellspacing=0  style='font-size: 0.8em;'>
                <tr height=30>
                    <th>CLAIM NUMBER</th>
                    <th>ASSURED</th>
                    <th>LOSS DATE</th>
                    <th>LOSS RESERVE</th>
                    <th>PAYMENT</th>
                    <th>CHECK DATE</th>
                    <th>CLAIM STATUS</th>
                    <th>USER</th>
                    <th>AGING</th>
                </tr>";

oci_execute($result);
while ($row = oci_fetch_assoc($result)) {
    $CLAIM        = $row['CLAIM'];
    $ASSURED_NAME = $row['ASSURED_NAME'];
    $IN_HOU_ADJ   = $row['IN_HOU_ADJ'];
    $LOSS_DATE    = $row['LOSS_DATE'];
    $CLM_STAT_DESC = $row['CLM_STAT_DESC'];
    $AGING        = $row['AGING'];
    $PAYT         = $row['PAYT_AMT'];
 
    echo "<tr>
              <td width=200>$CLAIM</td>
              <td width=350>$ASSURED_NAME</td>
              <td align=center width=100>$LOSS_DATE</td>
              <td align=right width=108>{$row['LOSS_RESERVE']}</td>
              <td align=right width=108>$PAYT</td>
              <td align=right width=108>{$row['CHECK_DATE']}</td>    
              <td width=250>$CLM_STAT_DESC</td>
              <td>$IN_HOU_ADJ</td>
              <td align=center>$AGING</td>
         </tr>";
}
?>

