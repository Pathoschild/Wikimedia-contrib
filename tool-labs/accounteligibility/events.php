<?php

/**
 * Provides events available for eligibility checks.
 */
class EventFactory
{
    ##########
    ## Public methods
    ##########
    /**
     * Get all available events.
     */
    public function getEvents()
    {
        ##########
        ## 2016: Commons Picture of the Year for 2015
        ##########
        yield new Event(39, 2016, 'Commons Picture of the Year for 2015', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2015');


        ##########
        ## 2016: steward elections
        ##########
        // voters
        yield new Event(38, 2016, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2016');

        // candidates
        yield (new Event(37, 2016, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2016'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                'You must <a href="https://meta.wikimedia.org/wiki/Special:MyLanguage/Access_to_nonpublic_information_policy" title="Access to nonpublic information policy">sign the confidentiality agreement</a>.'
            ]);


        ##########
        ## 2015: Wikimedia Foundation elections
        ##########
        yield (new Event(36, 2015, 'Wikimedia Foundation elections', '//meta.wikimedia.org/wiki/Wikimedia_Foundation_elections_2015'))
            ->withExtraRequirements(['Your account must not be used by a bot.'])
            ->withExceptions([
                'You are a Wikimedia server administrator with shell access.',
                'You have commit access and have made at least one merged commit in git to Wikimedia Foundation utilized repos between 15 October 2014 and 15 April 2015.',
                'You are a current Wikimedia Foundation staff member or contractor employed by the Foundation as of 15 April 2015.',
                'You are a current or former member of the Wikimedia Board of Trustees, Advisory Board or Funds Dissemination Committee.'
            ])
            ->withMinEditsForAutoselect(300);


        ##########
        ## 2015: steward elections
        ##########
        // voters
        yield new Event(35, 2015, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2015');

        // candidates
        yield (new Event(34, 2015, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2015'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2015.'
            ]);


        ##########
        ## 2015: Commons Picture of the Year for 2014
        ##########
        yield new Event(33, 2015, 'Commons Picture of the Year for 2014', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2014');


        ##########
        ## 2014: Commons Picture of the Year for 2013
        ##########
        yield new Event(32, 2014, 'Commons Picture of the Year for 2013', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2013');


        ##########
        ## 2014: steward elections
        ##########
        // voters
        yield new Event(31, 2014, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2014');

        // candidates
        yield (new Event(30, 2014, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2014'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2014.'
            ]);


        ##########
        ## 2013: steward elections
        ##########
        // voters
        yield new Event(29, 2013, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2013');

        // candidates
        yield (new Event(28, 2013, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2013'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2013.'
            ]);


        ##########
        ## 2013: Commons Picture of the Year for 2012
        ##########
        yield new Event(27, 2013, 'Commons Picture of the Year for 2012', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2012');


        ##########
        ## 2012: enwiki arbcom elections
        ##########
        // voters
        yield (new Event(26, 2012, 'enwiki arbcom elections (voters)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012'))
            ->withOnlyDB('enwiki_p');

        // candidates
        yield (new Event(25, 2012, 'enwiki arbcom elections (candidates)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012'))
            ->withOnlyDB('enwiki_p')
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be in good standing and not subject to active blocks or site-bans.',
                'You must meet the Wikimedia Foundation\'s <a href="//wikimediafoundation.org/w/index.php?title=Access_to_nonpublic_data_policy&oldid=47490" title="Access to nonpublic data policy">criteria for access to non-public data</a> and must identify with the Foundation if elected.',
                'You must have disclosed any alternate accounts in your election statement (legitimate accounts which have been declared to the Arbitration Committee before the close of nominations need not be publicly disclosed).'
            ]);


        ##########
        ## 2012: Commons Picture of the Year for 2011
        ##########
        yield new Event(24, 2012, 'Commons Picture of the Year for 2011', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2011');


        ##########
        ## 2012: steward elections
        ##########
        // voters
        yield new Event(23, 2012, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2012');

        // candidates
        yield (new Event(22, 2012, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2012'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2012.'
            ]);


        ##########
        ## 2011: enwiki arbcom elections
        ##########
        // voters
        yield (new Event(21, 2011, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2011'))
            ->withOnlyDB('enwiki_p');

        // candidates
        yield (new Event(20, 2011, 'enwiki arbcom elections (candidates)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2011'))
            ->withOnlyDB('enwiki_p')
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be in good standing and not subject to active blocks or site-bans.',
                'You must meet the Wikimedia Foundation\'s criteria for access to non-public data and must identify with the Foundation if elected.',
                'You must have disclosed any alternate accounts in your election statement (legitimate accounts which have been declared to the Arbitration Committee prior to the close of nominations need not be publicly disclosed).'
            ]);


        ##########
        ## 2011: steward elections (September)
        ##########
        // voters
        yield new Event(19, 2011, '2011-09 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2011-2');

        // candidates
        yield (new Event(18, 2011, '2011-09 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2011-2'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 07 February 2011.'
            ]);


        ##########
        ## 2011: Wikimedia board elections
        ##########
        yield (new Event(17, 2011, 'Board elections', '//meta.wikimedia.org/wiki/Board elections/2011'))
            ->withMinEditsForAutoselect(300)
            ->withExtraRequirements(['Your account must not be used by a bot.'])
            ->withExceptions([
                'You are a Wikimedia server administrator with shell access.',
                'You have MediaWiki commit access and made at least one commit between 15 May 2010 and 15 May 2011.',
                'You are a Wikimedia Foundation staff or contractor employed by Wikimedia between 15 February 2011 and 15 May 2011.',
                'You are a current or former member of the Wikimedia Board of Trustees or Advisory Board.'
            ]);


        ##########
        ## 2011: Commons Picture of the Year for 2010
        ##########
        yield new Event(16, 2011, 'Commons Picture of the Year for 2010', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2010');


        ##########
        ## 2011: steward elections (January)
        ##########
        // confirmation discussions
        yield (new Event(15, 2011, '2011-01 steward confirmations', '//meta.wikimedia.org/wiki/Stewards/confirm/2011'))
            ->withAction('comment');

        // voters
        yield new Event(14, 2011, '2011-01 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2011');

        // candidates
        yield (new Event(13, 2011, '2011-01 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2011'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 07 February 2011.'
            ]);


        ##########
        ## 2010: enwiki arbcom elections
        ##########
        yield (new Event(12, 2010, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2010'))
            ->withOnlyDB('enwiki_p');


        ##########
        ## 2010: steward elections (September)
        ##########
        yield new Event(11, 2010, '2010-09 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2010-2');

        // candidates
        yield (new Event(10, 2010, '2010-09 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2010-2'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
            ]);


        ##########
        ## 2010: Commons Picture of the Year for 2009
        ##########
        yield new Event(9, 2010, 'Commons Picture of the Year for 2009', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2009');


        ##########
        ## 2010: steward elections (February)
        ##########
        // voters
        yield (new Event(8, 2010, '2010-02 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2010'))
            ->withExtraRequirements(['Your account must not be primarily used for automated (bot) tasks.']);

        // candidates
        yield (new Event(7, 2010, '2010-02 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2010'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
            ]);


        ##########
        ## 2010: create global sysops vote
        ##########
        yield new Event(6, 2010, 'create global sysops vote', '//meta.wikimedia.org/wiki/Global_sysops/Vote');


        ##########
        ## 2009: enwiki arbcom elections
        ##########
        yield (new Event(5, 2009, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2009'))
            ->withonlyDB('enwiki_p');


        ##########
        ## 2009: Commons Picture of the Year for 2008
        ##########
        yield new Event(4, 2009, 'Commons Picture of the Year for 2008', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2008');


        ##########
        ## 2009: steward elections
        ##########
        // candidates
        yield (new Event(3, 2009, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2009'))
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation.'
            ]);

        // voters
        yield (new Event(2, 2009, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2009'))
            ->withMinEditsForAutoselect(600);


        ##########
        ## 2008: enwiki arbcom elections
        ##########
        yield (new Event(1, 2008, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2008'))
            ->withOnlyDB('enwiki_p');


        ##########
        ## 208: Wikimedia board elections
        ##########
        yield (new Event(0, 2008, 'Board elections', '//meta.wikimedia.org/wiki/Board elections/2008'))
            ->withMinEditsForAutoselect(600);
    }
}