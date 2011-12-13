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
    public function writeMeta($totalitems, $itemsperpage = '100', $message = '', $status = 'ok', $statuscode = '100')
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
     * Dumps meta element when error occured (ie. when status is not ok)
     */
    public function writeError($message = '', $statuscode = '101')
    {
        $this->startElement('meta');
        $this->writeElement('status', 'nok');
        $this->writeElement('statuscode', $statuscode);
        $this->writeElement('message', $message);
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

        foreach ($packages as $package)
        {
            $mvc = midgardmvc_core::get_instance();

            $this->startElement('content');
            $this->writeAttribute('details','full');
            $this->writeElement('id',              $package->packageid);
            $this->writeElement('name',            $package->packagetitle);
            $this->writeElement('version',         $package->packageversion);
            $this->writeElement('description',     $package->packagedescription);
            $this->writeElement('summary',         $package->packagesummary);
            $this->writeElement('homepage',        $package->packagehomepageurl);
            $this->writeElement('created',         $package->packagecreated);
            $this->writeElement('changed',         $package->packagerevised);
            $this->writeElement('score',           20 * $package->statscachedratingvalue);
            $this->writeElement('x-roles',         $package->roles);
            $this->writeElement('x-filename',      $package->packagefilename);
            $this->writeElement('x-license',       $package->packagelicense);
            $this->writeElement('x-arch',          $package->repoarch);
            $this->writeElement('x-project',       $package->repoprojectname);
            $this->writeElement('x-repository',    $package->reponame);
            $this->writeElement('x-os',            $package->repoos);
            $this->writeElement('x-osversion',     $package->repoosversion);
            $this->writeElement('x-ux',            $package->repoosux);
            $this->writeElement('x-licenseid',     $package->packagelicenseid);
            $this->writeElement('x-distributionid',$package->repoosversionid);
            $this->writeElement('x-dependencyid',  $package->repoosuxid);
            $this->writeElement('x-obsname',       $package->packageparent);

            if (   isset($package->testing)
                && $package->testing)
            {
                $this->writeElement('x-testing', true);
            }

            $dispatcher = midgardmvc_core::get_instance()->dispatcher;

            $counter = 0;

            $_downloadurl = '';//$package->packageinstallfileurl;

            foreach ($package->attachments as $attachment)
            {
                // check if attachment is YMP (ie. 1 click install file)
                if (   $attachment->mimetype == "text/x-suse-ymp"
                    && ! strlen($_downloadurl))
                {
                    $_downloadurl = com_meego_ocs_controllers_providers::generate_url(
                        $dispatcher->generate_url(
                            'attachmentserver_variant',
                            array(
                                'guid' => $attachment->guid,
                                'variant' => '',
                                'filename' => $attachment->name,
                            ),
                            '/'
                        )
                    );
                }

                // check if attachment MIME type is image something
                if ($attachment->mimetype == "image/png")
                {
                    $_icon_marker = 'icon.png';
                    $_screenshot_marker = 'screenshot.png';

                    // check if the name is *screenshot.png
                    if (strrpos($attachment->name, $_screenshot_marker) !== false)
                    {
                        $counter++;
                        $_screenshoturl = com_meego_ocs_controllers_providers::generate_url(
                            $dispatcher->generate_url(
                                'attachmentserver_variant',
                                array(
                                    'guid' => $attachment->guid,
                                    'variant' => 'prop480x300',
                                    'filename' => $attachment->name,
                                ),
                                '/'
                            )
                        );

                        $_smallscreenshoturl = com_meego_ocs_controllers_providers::generate_url(
                            $dispatcher->generate_url(
                                'attachmentserver_variant',
                                array(
                                    'guid' => $attachment->guid,
                                    'variant' => 'thumbnail',
                                    'filename' => $attachment->name,
                                ),
                                '/'
                            )
                        );

                        $this->writeElement('previewpic'.$counter,      $_screenshoturl);
                        $this->writeElement('smallpreviewpic'.$counter, $_smallscreenshoturl);

                        #if ($counter == 3)
                        #{
                        #    break;
                        #}
                    }

                    // check if the name is *icon.png and generate <icon> elements
                    if (strrpos($attachment->name, $_icon_marker) !== false)
                    {
                        $_iconurl = com_meego_ocs_controllers_providers::generate_url(
                            $dispatcher->generate_url(
                                'attachmentserver_variant',
                                array(
                                    'guid' => $attachment->guid,
                                    'variant' => 'icon',
                                    'filename' => $attachment->name,
                                ),
                                '/'
                            )
                        );

                        $iconwidth = $mvc->configuration->attachmentserver_variants['icon']['croppedThumbnail']['width'];
                        $iconheight = $mvc->configuration->attachmentserver_variants['icon']['croppedThumbnail']['height'];
                        $this->startElement('icon');
                        $this->writeAttribute('width', $iconwidth);
                        $this->writeAttribute('height', $iconheight);
                        $this->text($_iconurl);
                        $this->endElement();
                    }
                }
            }

            $this->writeElement('comments', $package->comments_count);

            if (isset($package->commentsurl))
            {
                $this->writeElement('commentspage', $package->commentsurl);
            }

            $this->writeElement('downloadname1', $package->packagename);
            $this->writeElement('downloadlink1', $_downloadurl);

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
            $this->writeElement('x-version', $obj->version);
            $this->writeElement('x-arch', $obj->arch);
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
            $this->writeElement('id', $obj->id);
            $this->writeElement('name', $obj->name);
            $this->writeElement('link', $obj->url);
            $this->endElement();
        }

        $this->endElement(); // data
    }

    /**
     * Dumps dependency elements
     */
    public function writeDependencies($list)
    {
        $this->startElement('data');

        foreach ($list as $obj)
        {
            $this->startElement('dependency');
            $this->writeElement('id', $obj->id);
            $this->writeElement('name', $obj->name);
            $this->endElement(); // distribution
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
