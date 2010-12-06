<?php

class com_meego_ocs_OCSWriter extends XMLWriter
{
    public function __construct()
    {
        $this->openMemory();
        $this->startElement('ocs');
    }

    public function endDocument()
    {
        $this->endElement(); // ocs
        parent::endDocument();
    }

    public function writeMeta($totalitems, $message = '', $status = 'ok', $statuscode = '100')
    {
        $this->startElement('meta');
        $this->writeElement('status', $status);
        $this->writeElement('statuscode', $statuscode);
        $this->writeElement('message', $message);
        $this->writeElement('totalitems', $totalitems);
        $this->endElement(); // meta
    }

    public function writeCategories($list)
    {
        $this->startElement('data');
        foreach ($list as $obj) {
            $this->startElement('category');
            $this->writeElement('id', $obj->id);
            $this->writeElement('name', $obj->name);
            $this->endElement(); // category
        }
        $this->endElement(); // data
    }

    public function writeContent($packages)
    {
        $this->startElement('data');

        foreach ($packages as $package)
        {
            $this->startElement('content');
            $this->writeAttribute('details','full');
            $this->writeElement('id',            $package->id);
            $this->writeElement('name',          $package->name);
            $this->writeElement('version',       $package->version);
            $this->writeElement('description',   $package->description);
            $this->writeElement('summary',       $package->summary);
            $this->writeElement('homepage',      $package->url);
            $this->writeElement('created',       $package->metadata->created);
            $this->writeElement('changed',       $package->metadata->revised);

            $dispatcher = midgardmvc_core::get_instance()->dispatcher;

            $counter = 0;
            foreach ($package->attachments as $attachment)
            {
                $counter++;
                $_screenshoturl = $dispatcher->generate_url(
                    'attachmentserver_variant',
                    array(
                        'guid' => $attachment->guid,
                        'variant' => 'sidesquare',
                        'filename' => $attachment->name,
                    ),
                    '/'
                );

                $_smallscreenshoturl = $dispatcher->generate_url(
                    'attachmentserver_variant',
                    array(
                        'guid' => $attachment->guid,
                        'variant' => 'thumbnail',
                        'filename' => $attachment->name,
                    ),
                    '/'
                );

                $this->writeElement('previewpic'.$counter,      $_screenshoturl);
                $this->writeElement('smallpreviewpic'.$counter, $_smallscreenshoturl);

                if ($counter == 3)
                    break;
            }

            $this->writeElement('comments', $package->comments_count);
            $this->endElement(); //content
        }
        $this->endElement(); // data
    }

    public function writeDistributions($list)
    {
        $this->startElement('data');

        foreach ($list as $obj) {
            $this->startElement('distribution');
            $this->writeElement('id', $obj->id);
            $this->writeElement('name', $obj->name);
            $this->endElement(); // distribution
        }

        $this->endElement(); // data
    }

    public function writeEmptyData()
    {
        $this->startElement('data');
        $this->endElement(); // data
    }
}
