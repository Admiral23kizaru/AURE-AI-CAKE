<?php
declare(strict_types=1);
function load_env(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line=trim($line); if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$name,$value]=array_map('trim',explode('=',$line,2)); if ($name==='' || getenv($name)!==false) continue;
        if (strlen($value)>=2 && (($value[0]==='"' && substr($value,-1)==='"') || ($value[0]==="'" && substr($value,-1)==="'"))) $value=substr($value,1,-1);
        putenv($name.'='.$value); $_ENV[$name]=$value;
    }
}
function env_value(string $name, ?string $default=null): ?string { $value=getenv($name); return $value===false?$default:$value; }
function env_bool(string $name, bool $default=false): bool { $value=env_value($name); return $value===null?$default:filter_var($value,FILTER_VALIDATE_BOOLEAN); }
load_env(dirname(__DIR__).'/.env');
