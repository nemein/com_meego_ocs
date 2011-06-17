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

    /**
     * Dumps meta element
     */
    public function writeMeta($totalitems, $message = '', $status = 'ok', $statuscode = '100', $itemsperpage = '100')
    {
        $this->startElement('meta');
        $this->writeElement('status', $status);
        $this->writeElement('statuscode', $statuscode);
        $this->writeElement('message', $message);
        $this->writeElement('totalitems', $totalitems);
        $this->writeElement('itemsperpage', $itemsperpage);
        $this->endElement(); // meta
    }

    /**
     * Dumps category elements
     */
    public function writeCategories($list)
    {
        $this->startElement('data');

        foreach ($list as $obj)
        {
            $this->startElement('category');
            $this->writeElement('id', $obj->id);
            $this->writeElement('name', $obj->name);
            $this->endElement(); // category
        }

        $this->endElement(); // data
    }

    /**
     * Dumps content elements
     */
    public function writeContent($packages)
    {
        $this->startElement('data');

        $urlbase = midgardmvc_core::get_instance()->configuration->base_url;

        foreach ($packages as $package)
        {
            $this->startElement('content');
            $this->writeAttribute('details','full');
            $this->writeElement('id',            $package->packageid);
            $this->writeElement('name',          $package->packagename);
            $this->writeElement('version',       $package->packageversion);
            $this->writeElement('description',   $package->packagedescription);
            $this->writeElement('summary',       $package->packagesummary);
            $this->writeElement('homepage',      $package->packagehomepageurl);
            $this->writeElement('created',       $package->packagecreated);
            $this->writeElement('changed',       $package->packagerevised);

            $dispatcher = midgardmvc_core::get_instance()->dispatcher;

            $counter = 0;
            foreach ($package->attachments as $attachment)
            {
                $counter++;
                $_screenshoturl = $urlbase . $dispatcher->generate_url(
                    'attachmentserver_variant',
                    array(
                        'guid' => $attachment->guid,
                        'variant' => 'sidesquare',
                        'filename' => $attachment->name,
                    ),
                    '/'
                );

                $_smallscreenshoturl = $urlbase . $dispatcher->generate_url(
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

            if (isset($package->commentsurl))
            {
                $this->writeElement('commentspage', $package->commentsurl);
            }

            $this->endElement(); //content
        }
        $this->endElement(); // data
    }

    /**
     * Dumps distributions elements
     */
    public function writeDistributions($list)
    {
        $this->startElement('data');

        foreach ($list as $obj)
        {
            $this->startElement('distribution');
            $this->writeElement('id', $obj->id);
            $this->writeElement('name', $obj->name);
            $this->endElement(); // distribution
        }

        $this->endElement(); // data
    }

    /**
     * Dumps license elements
     */
    public function writeLicenses($list)
    {
        $this->startElement('data');

        foreach ($list as $obj)
        {
            $this->startElement('licenses');
            $this->writeElement('id', $obj['id']);
            $this->writeElement('name', $obj['name']);
            $this->writeElement('link', $obj['link']);
            $this->endElement();
        }

        $this->endElement(); // data
    }

    /**
     * Dumps an empty data element
     */
    public function writeEmptyData()
    {
        $this->startElement('data');
        $this->endElement(); // data
    }
}
