<html> 
    <head>
        <meta charset="UTF-8">
        <title>Claim Aging</title>
        <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
    </head>
    <body>

<?php
session_start();
error_reporting(E_ALL);
//ini_set('display_errors', '1');
include "include/db_config_ora.php";
$OraDB = oci_connect("cpi", "cpi12345!", "10.18.0.218/PCIC");
$branch = $_GET["branch"];
$line   = $_GET["line"];

$result = oci_parse($OraDB,"SELECT get_claim_number(a.claim_id)claim, assured_name,in_hou_adj, grp_iss_cd branch, 
                                   to_char(loss_date,'MM-DD-YYYY')loss_date, to_char(a.last_update,'MM-DD-YYYY')last_update, 
                                   d.clm_stat_desc , 
                                   trim(to_char(calculate_business_days(A.LAST_UPDATE,SYSDATE),'999,999'))  aging
                            FROM GICL_CLAIMS a, giis_users b
                                ,GIIS_USER_GRP_HDR c, GIIS_CLM_STAT d 
                            WHERE a.in_hou_adj =b.user_id
                              AND b.user_grp = c.user_grp
                              AND b.active_flag = 'Y'
                              AND a.clm_stat_cd NOT IN('CD','CC','WD','DN')
                              AND calculate_business_days(A.LAST_UPDATE,SYSDATE) >= 7
                              AND c.grp_iss_cd  = NVL('$branch',C.grp_iss_cd)
                               AND a.line_cd = NVL('$line',a.line_cd)
                              AND a.clm_stat_cd = d.clm_stat_cd
                         ORDER BY calculate_business_days(A.LAST_UPDATE,SYSDATE) desc 
                                   , a.line_cd, grp_iss_cd,   d.clm_stat_desc
                                   , in_hou_adj, get_claim_number(a.claim_id) ");



echo "<font size=3><b><br>List of Non-Moving Claim/s Overdue</b></font><br>";
date_default_timezone_set("Asia/Hong_Kong");
echo date("l\, F jS\, Y ", time());

   
echo "<br>";
echo "<br>";
echo " <table class='table table-bordered' border = 1 width=100% align=center cellpadding=0 cellspacing=0  style='font-size: 0.8em;'>
                <tr height=30>
                    <th>CLAIM NUMBER</th>
                    <th>ASSURED</th>
                    <th>LOSS DATE</th>
                    <th>LAST UPDATE</th>
                    <th>CLAIM STATUS</th>
                    <th>USER</th>
                    <th>AGING</th>
                </tr>";
oci_execute($result);
while ($row = oci_fetch_assoc($result)){
    $CLAIM = $row['CLAIM'];
    $ASSURED_NAME = $row['ASSURED_NAME'];
    $IN_HOU_ADJ = $row['IN_HOU_ADJ'];
    $LOSS_DATE = $row['LOSS_DATE'];
    $LAST_UPDATE = $row['LAST_UPDATE'];
    $CLM_STAT_DESC = $row['CLM_STAT_DESC'];
    $AGING = $row['AGING'];
    
    echo "<tr>
                    <td width=200>$CLAIM</td>
                    <td width=350>$ASSURED_NAME</td>
                    <td align=center width=100>$LOSS_DATE</td>
                    <td align=center width=108>$LAST_UPDATE</td>
                    <td width=250>$CLM_STAT_DESC</td>
                    <td>$IN_HOU_ADJ</td>
                    <td align=center>$AGING</td>
                </tr>";
}


?>

