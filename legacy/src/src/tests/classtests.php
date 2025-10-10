//// #!/usr/bin/env php
//// 
//// <?php
//// 
//// require __DIR__ . '/../../vendor/autoload.php';
//// 
//// class media implements MongoDB\BSON\Persistable
//// {
////     public string $extension;
////     public string $fileName;
////     public string $dirName;
////     public string $filepath;
//// 
////     public static function fromFilePath(string $filepath)
////     {
////         $tmp = explode('.', $filepath);
////         $extension = strtolower(end($tmp));
////         var_dump($extension);
//// 
////         $tmp2 = explode('/', $filepath);
////         $filename = end($tmp2);
////         $dirname = dirname($filepath);
//// 
////         $obj = new self();
////         switch ($extension) {
////             case 'mp4':
////                 $obj = video::fromFilePath($filepath);
////                 break;
////                 // $obj->extension = $extension;
////                 // return $obj;
////             case 'jpg':
////                 $obj =  image::fromFilePath($filepath);
////                 break;
////                 // $obj->extension = $extension;
////                 // return $obj;
////         }
//// 
////         $obj->extension = $extension;
////         $obj->filename = $filename;
////         $obj->dirname = $dirname;
////         $obj->filepath = $filepath;
//// 
////         return $obj;
////     }
//// 
////     public function bsonSerialize()
////     {
////         return [
////             'extension' => $this->extension,
////             'filename' => $this->filename,
////             'dirname' => $this->dirname,
////             'filepath' => $this->filepath
////         ];
////     }
//// 
////     public function bsonUnserialize(array $data)
////     {
////         $this->extension = $data['extension'];
////         $this->filename = $data['filename'];
////         $this->dirname = $data['dirname'];
////         $this->filepat = $data['filepath'];
////     }
//// }
//// 
//// class video extends media
//// {
////     public int $bitrate;
//// 
////     public static function fromFilePath(string $filepath)
////     {
////         $instance = new self();
////         $instance->bitrate = 1299;
////         return $instance;
////     }
//// }
//// 
//// class image extends media
//// {
////     public string $orientation;
//// 
////     public static function fromFilePath(string $filepath)
////     {
////         $instance = new self();
////         $instance->orientation = 'PORTRAIT';
////         return $instance;
////     }
//// }
//// 
//// $path = '/tmp/test.mp4';
//// 
//// $obj = media::fromFilePath($path);
//// 
//// // var_dump($obj);
//// 
//// var_dump($obj instanceof video);
//// var_dump($obj instanceof image);
//// 
//// 
//// $client = new MongoDB\Client("mongodb://root:example@localhost:27017");
//// $collection = $client->test->media;
//// 
//// $collection->deleteMany(['filepath' => $path]);
//// 
//// $id = $collection->InsertOne($obj);
//// 
//// 
//// $fromdbobj = $collection->findOne(['filepath' => $path]);
//// var_dump($fromdbobj);
//// 
//// var_dump($fromdbobj instanceof video);
//// // $phpobj = $fromdbobj->toPHP();
//// 
//// // var_dump($phpobj);
//// 
//// // var_dump($fromdbobj);