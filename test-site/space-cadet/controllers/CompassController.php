<?php
class CompassController {
    public function schema(Request $req, int $collectionId): void {
        Auth::requireRole('editor');
        $fields=Collection::fields($collectionId);
        $filterable=[];
        foreach($fields as $f){
            $type=$f['type'];
            if(in_array($type,['select','checkbox'],true)) $filterable[]=['key'=>$f['key'],'name'=>$f['name'],'type'=>'dropdown','options'=>json_decode($f['options']??'{}',true)];
            elseif($type==='toggle') $filterable[]=['key'=>$f['key'],'name'=>$f['name'],'type'=>'boolean'];
            elseif($type==='number') $filterable[]=['key'=>$f['key'],'name'=>$f['name'],'type'=>'range'];
            elseif($type==='date') $filterable[]=['key'=>$f['key'],'name'=>$f['name'],'type'=>'daterange'];
        }
        // Always include labels and status
        $filterable[]=['key'=>'_label','name'=>'Labels','type'=>'labels'];
        $filterable[]=['key'=>'_status','name'=>'Status','type'=>'status','options'=>['draft','published','archived']];
        Response::success($filterable);
    }
}
