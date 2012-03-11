<?php

class com_meego_ocs_OCSWriter extends XMLWriter
{
    public $error = false;

    public function __construct()
    {
        $this->openMemory();
        parent::startDocument('1.0', 'UTF-8');
        $this->startElement('ocs');
        $this->error = false;
    }

    public function endDocument()
    {
        $this->error = false;
        $this->endElement(); // ocs
        parent::endDocument();
    }

    /**
     * Dumps meta element
     */
    public function writeMeta($totalitems = null, $itemsperpage = null, $message = '', $status = 'ok', $statuscode = '100')
    {
        $this->startElement('meta');
        $this->writeElement('status', $status);
        $this->writeElement('statuscode', $statuscode);
        $this->writeElement('message', $message);
        if ($totalitems)
        {
            $this->writeElement('totalitems', $totalitems);
        }
        if ($itemsperpage)
        {
            $this->writeElement('itemsperpage', $itemsperpage);
        }
        $this->endElement(); // meta
    }

    /**
     * Dumps meta element when error occured (ie. when status is not ok)
     */
    public function writeError($message = '', $statuscode = '101')
    {
        $this->error = true;
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
            $this->writeElement('x-history',       $package->history);

            $user = com_meego_ocs_utils::get_current_user();
            if ($user)
            {
                if (com_meego_ocs_utils::user_has_voted($package->packageid, $user->person))
                {
                    $this->writeElement('x-rated', 'true');
                }
                else
                {
                    $this->writeElement('x-rated', 'false');
                }
            }

            if (   isset($package->testing)
                && $package->testing)
            {
                $this->writeElement('x-testing', true);

                if ($package->qa)
                {
                    $this->writeElement('x-qa', $package->qa);
                }
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

    /**
     * Writes a person check element
     *
     *   <person details="check">
     *      <personid>login</personid>
     *      <x-email>email</x-email>
     *    </person>
     */
    public function writePersonCheck($login = null, $email = null)
    {
        if (! $login)
        {
            return;
        }

        $this->startElement('data');
        $this->startElement('person');
        $this->writeAttribute('details', 'check');
        $this->writeElement('personid', $login);
        if ($email)
        {
            $this->writeElement('x-email', $email);
        }
        $this->endElement(); // person
        $this->endElement(); // data
    }
}
