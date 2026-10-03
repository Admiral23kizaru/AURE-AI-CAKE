<?php declare(strict_types=1);require_once __DIR__.'/env.php';return ['secret_key'=>(string)env_value('XENDIT_SECRET_KEY',''),'webhook_token'=>(string)env_value('XENDIT_WEBHOOK_TOKEN','')];
