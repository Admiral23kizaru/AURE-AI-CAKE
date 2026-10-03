<?php
declare(strict_types=1);
function acquire_cli_lock(string $name)
{
    $path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'ai-cake-'.preg_replace('/[^a-z0-9_-]/i','-',$name).'.lock';
    $handle=fopen($path,'c');
    if(!$handle||!flock($handle,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Another {$name} process is already running.\n");exit(0);}
    ftruncate($handle,0);fwrite($handle,(string)getmypid());
    return $handle;
}
