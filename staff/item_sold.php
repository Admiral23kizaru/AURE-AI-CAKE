<?php declare(strict_types=1);$query=$_GET;header('Location: ../admin/combined_reports.php'.($query?'?'.http_build_query($query):''),true,302);exit;
