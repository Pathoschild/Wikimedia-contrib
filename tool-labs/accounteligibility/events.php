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
     * Get the event ID to select by default.
     * @return int
     */
    public function getDefaultEventID()
    {
        return 42;
    }

    /**
     * Get all available events.
     */
    public function getEvents()
    {

        ##########
        ## 2017: Wikimedia Foundation elections
        ##########
        yield (new Event(44, 2017, 'Wikimedia Foundation elections', '//meta.wikimedia.org/wiki/Wikimedia Foundation elections/2017'))
            ->addRule(new NotBlockedRule(1), Workflow::HARD_FAIL)// not blocked on more than one wiki
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(300, null, '<20170401', EditCountRule::ACCUMULATE))// 300 edits before 1 April 2017
            ->addRule(new EditCountRule(20, '20161001', '<20170401', EditCountRule::ACCUMULATE))// 20 edits between 1 October 2016 and 1 April 2017
            ->withExtraRequirements(['Your account must not be used by a bot.'])
            ->withExceptions([
                'You are a Wikimedia server administrator with shell access.',
                'You have commit access and have made at least one merged commit in git to Wikimedia Foundation utilized repos between 1 October 2016 and 1 April 2017.',
                'You are a current Wikimedia Foundation staff member or contractor employed by the Foundation as of 1 April 2017.',
                'You are a current staff member or contractor employed by an approved Wikimedia Chapter, Thematic Organization or User Group as of 1 April 2017.',
                'You are a current or former member of the Wikimedia Board of Trustees, Advisory Board or Funds Dissemination Committee.'
            ]);

        ##########
        ## 2017: Wikimedia Foundation elections (candidates)
        ##########
        yield (new Event(43, 2017, 'Wikimedia Foundation elections (candidates)', '//meta.wikimedia.org/wiki/Wikimedia Foundation elections/2017'))
            ->addRule(new NotBlockedRule(1), Workflow::HARD_FAIL)// not blocked on more than one wiki
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new DateRegisteredRule('<20150401'), Workflow::ON_ANY_WIKI)// registered before 01 April 2015
            ->addRule(new EditCountRule(300, null, '<20170401', EditCountRule::ACCUMULATE))// 300 edits before 1 April 2017
            ->addRule(new EditCountRule(20, '20161001', '<20170401', EditCountRule::ACCUMULATE))// 20 edits between 1 October 2016 and 1 April 2017
            ->withExtraRequirements([
                'Your account must not be used by a bot.',
                'Your first edit must be before 1 April 2015'
            ])
            ->withExceptions([
                'You are a Wikimedia server administrator with shell access.',
                'You have commit access and have made at least one merged commit in git to Wikimedia Foundation utilized repos between 1 October 2016 and 1 April 2017.',
                'You are a current Wikimedia Foundation staff member or contractor employed by the Foundation as of 1 April 2015.',
                'You are a current staff member or contractor employed by an approved Wikimedia Chapter, Thematic Organization or User Group as of 1 April 2017.',
                'You are a current or former member of the Wikimedia Board of Trustees, Advisory Board or Funds Dissemination Committee.'
            ]);
        
        ##########
        ## 2017: Commons Picture of the Year for 2016
        ##########
        yield (new Event(42, 2017, 'Commons Picture of the Year for 2016', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2016'))
            ->addRule(new DateRegisteredRule('<201701'), Workflow::ON_ANY_WIKI)// registered before 01 January 2017
            ->addRule(new EditCountRule(75, null, '<201701'), Workflow::ON_ANY_WIKI);// 75 edits before 01 January 2017


        ##########
        ## 2017: steward elections
        ##########
        // voters
        yield (new Event(41, 2017, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2017'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201611', EditCountRule::ACCUMULATE | EditCountRule::COUNT_DELETED))// 600 edits before 01 November 2016
            ->addRule(new EditCountRule(50, '201608', '<201702', EditCountRule::ACCUMULATE | EditCountRule::COUNT_DELETED));// 50 edits between 01 August 2016 and 31 January 2017

        // candidates
        yield (new Event(40, 2017, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2017'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201611', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2016
            ->addRule(new EditCountRule(50, '201608', '<201702', EditCountRule::ACCUMULATE))// 50 edits between 01 August 2016 and 31 January 2017
            ->addRule(new HasGroupDurationRule('sysop', 90, '<201702'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at least the age of majority in your country before the final day of voting.',
                'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                'You must <a href="https://meta.wikimedia.org/wiki/Special:MyLanguage/Access_to_nonpublic_information_policy" title="Access to nonpublic information policy">sign the confidentiality agreement</a>.'
            ]);


        ##########
        ## 2016: Commons Picture of the Year for 2015
        ##########
        yield (new Event(39, 2016, 'Commons Picture of the Year for 2015', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2015'))
            ->addRule(new DateRegisteredRule('<201601'), Workflow::ON_ANY_WIKI)// registered before 01 January 2016
            ->addRule(new EditCountRule(75, null, '<201601'), Workflow::ON_ANY_WIKI);// 75 edits before 01 January 2016


        ##########
        ## 2016: steward elections
        ##########
        // voters
        yield (new Event(38, 2016, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2016'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201511', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2015
            ->addRule(new EditCountRule(50, '201508', '<201602', EditCountRule::ACCUMULATE));// 50 edits between 01 August 2015 and 31 January 2016

        // candidates
        yield (new Event(37, 2016, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2016'))
            ->addRule(new DateRegisteredRule('<20150808'), Workflow::ON_ANY_WIKI)// registered for six months
            ->addRule(new HasGroupDurationRule('sysop', 90, '<20160208'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
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
            ->addRule(new NotBlockedRule(1), Workflow::HARD_FAIL)// not blocked on more than one wiki
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(300, null, '<20150415', EditCountRule::ACCUMULATE))// 300 edits before 15 April 2015
            ->addRule(new EditCountRule(20, '20141015', '<20150415', EditCountRule::ACCUMULATE))// 20 edits between 15 October 2014 and 15 April 2015
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
        yield (new Event(35, 2015, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2015'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201411', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2014
            ->addRule(new EditCountRule(50, '201408', '<201502', EditCountRule::ACCUMULATE));// 50 edits between 01 August 2014 and 31 January 2015

        // candidates
        yield (new Event(34, 2015, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2015'))
            ->addRule(new DateRegisteredRule('<20140808'), Workflow::ON_ANY_WIKI)// registered for six months
            ->addRule(new HasGroupDurationRule('sysop', 90, '<20150208'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2015.'
            ]);


        ##########
        ## 2015: Commons Picture of the Year for 2014
        ##########
        yield (new Event(33, 2015, 'Commons Picture of the Year for 2014', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2014'))
            ->addRule(new DateRegisteredRule('<201501'), Workflow::ON_ANY_WIKI)// registered before 01 January 2015
            ->addRule(new EditCountRule(75, null, '<201501'), Workflow::ON_ANY_WIKI);// 75 edits before 01 January 2015


        ##########
        ## 2014: Commons Picture of the Year for 2013
        ##########
        yield (new Event(32, 2014, 'Commons Picture of the Year for 2013', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2013'))
            ->addRule(new DateRegisteredRule('<201401'), Workflow::ON_ANY_WIKI)// registered before 01 January 2014
            ->addRule(new EditCountRule(75, null, '<201401'), Workflow::ON_ANY_WIKI);// 75 edits before 01 January 2014


        ##########
        ## 2014: steward elections
        ##########
        // voters
        yield (new Event(31, 2014, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2014'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201311', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2013
            ->addRule(new EditCountRule(50, '201308', '<201402', EditCountRule::ACCUMULATE));// 50 edits between 2013-Aug-01 and 2014-Jan-31

        // candidates
        yield (new Event(30, 2014, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2014'))
            ->addRule(new DateRegisteredRule('<20130808'), Workflow::ON_ANY_WIKI)// registered for six months
            ->addRule(new HasGroupDurationRule('sysop', 90, '<20140208'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
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
        yield (new Event(29, 2013, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2013'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201211', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2012
            ->addRule(new EditCountRule(50, '201208', '<201302', EditCountRule::ACCUMULATE));// 50 edits between 01 August 2012 and 31 January 2013

        // candidates
        yield (new Event(28, 2013, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2013'))
            ->addRule(new DateRegisteredRule('<20120808'), Workflow::ON_ANY_WIKI)// registered for six months
            ->addRule(new HasGroupDurationRule('sysop', 90, '<20130208'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2013.'
            ]);


        ##########
        ## 2013: Commons Picture of the Year for 2012
        ##########
        yield (new Event(27, 2013, 'Commons Picture of the Year for 2012', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2012'))
            ->addRule(new DateRegisteredRule('<201301'), Workflow::ON_ANY_WIKI)// registered before 01 January 2013
            ->addRule(new EditCountRule(75, null, '<201301'), Workflow::ON_ANY_WIKI);// 75 edits before 01 January 2013


        ##########
        ## 2012: enwiki arbcom elections
        ##########
        // voters
        yield (new Event(26, 2012, 'enwiki arbcom elections (voters)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012'))
            ->addRule(new NotBlockedRule())
            ->addRule((new EditCountRule(150, null, '<20121102'))->inNamespace(0))// 150 main-namespace edits before 02 Nov 2012
            ->withOnlyDB('enwiki_p');

        // candidates
        yield (new Event(25, 2012, 'enwiki arbcom elections (candidates)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012'))
            ->addRule(new NotBlockedRule())
            ->addRule((new EditCountRule(500, null, '<20121102'))->inNamespace(0))// 500 main-namespace edits before 02 November 2012
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
        yield (new Event(24, 2012, 'Commons Picture of the Year for 2011', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2011'))
            ->addRule(new DateRegisteredRule('<201204'), Workflow::ON_ANY_WIKI)// registered before 01 April 2012
            ->addRule(new EditCountRule(75, null, '<201204'), Workflow::ON_ANY_WIKI);// 75 edits before 01 April 2012


        ##########
        ## 2012: steward elections
        ##########
        // voters
        yield (new Event(23, 2012, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2012'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201111', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2011
            ->addRule(new EditCountRule(50, '201108', '<201202', EditCountRule::ACCUMULATE));// 50 edits between 01 August 2011 and 31 January 2012

        // candidates
        yield (new Event(22, 2012, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2012'))
            ->addRule(new DateRegisteredRule('<20110710'), Workflow::ON_ANY_WIKI)// registered for six months
            ->addRule(new HasGroupDurationRule('sysop', 90, '<20120129'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
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
            ->addRule(new NotBlockedRule())
            ->addRule((new EditCountRule(150, null, '<201111'))->inNamespace(0))// 150 main-namespace edits before 01 November 2011
            ->withOnlyDB('enwiki_p');

        // candidates
        yield (new Event(20, 2011, 'enwiki arbcom elections (candidates)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2011'))
            ->addRule(new NotBlockedRule())
            ->addRule((new EditCountRule(150, null, '<201111'))->inNamespace(0))// 150 main-namespace edits before 01 November 2011
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
        yield (new Event(19, 2011, '2011-09 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2011-2'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<20110615', EditCountRule::ACCUMULATE))// 600 edits before 15 June 2011
            ->addRule(new EditCountRule(50, '20110315', '<20110915', EditCountRule::ACCUMULATE));// 50 edits between 15 March 2011 and 14 September 2011

        // candidates
        yield (new Event(18, 2011, '2011-09 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2011-2'))
            ->addRule(new DateRegisteredRule('<20110314'), Workflow::ON_ANY_WIKI)// registered for six months
            ->addRule(new HasGroupDurationRule('sysop', 90, '<20110913'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
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
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new NotBlockedRule(1), Workflow::HARD_FAIL)// not blocked on more than one wiki
            ->addRule(new EditCountRule(300, null, '<20110415', EditCountRule::ACCUMULATE))// 300 edits before 15 April 2011
            ->addRule(new EditCountRule(20, '20101115', '<20110516', EditCountRule::ACCUMULATE))// 20 edits between 15 November 2010 and 15 May 2011
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
        yield (new Event(16, 2011, 'Commons Picture of the Year for 2010', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2010'))
            ->addRule(new DateRegisteredRule('<201101'), Workflow::ON_ANY_WIKI)// registered before 01 January 2011
            ->addRule(new EditCountRule(200, null, '<201101'), Workflow::ON_ANY_WIKI);// 200 edits before 01 January 2011


        ##########
        ## 2011: steward elections (January)
        ##########
        // confirmation discussions
        yield (new Event(15, 2011, '2011-01 steward confirmations', '//meta.wikimedia.org/wiki/Stewards/confirm/2011'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(1, null, '<201102', EditCountRule::ACCUMULATE))// one edit before 01 February 2011
            ->withAction('comment');

        // voters
        yield (new Event(14, 2011, '2011-01 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2011'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201011', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2010
            ->addRule(new EditCountRule(50, '201008', '<201102', EditCountRule::ACCUMULATE));// 50 edits between 01 August 2010 and 31 January 2011

        // candidates
        yield (new Event(13, 2011, '2011-01 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2011'))
            ->addRule(new DateRegisteredRule('<20100829'), Workflow::ON_ANY_WIKI)// registered for six months
            ->addRule(new HasGroupDurationRule('sysop', 90, '<20110129'), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
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
            ->addRule(new NotBlockedRule())
            ->addRule((new EditCountRule(150, null, '<20101102'))->inNamespace(0))// 150 main-namespace edits by 01 November 2010
            ->withOnlyDB('enwiki_p');


        ##########
        ## 2010: steward elections (September)
        ##########
        yield (new Event(11, 2010, '2010-09 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2010-2'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<201006', EditCountRule::ACCUMULATE))// 600 edits before 01 June 2010
            ->addRule(new EditCountRule(50, '201003', '<201009', EditCountRule::ACCUMULATE));// 50 edits between 01 March 2010 and 31 August 2010

        // candidates
        yield (new Event(10, 2010, '2010-09 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2010-2'))
            ->addRule(new DateRegisteredRule('<20100329'))// registered before 29 March 2010
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
            ]);


        ##########
        ## 2010: Commons Picture of the Year for 2009
        ##########
        yield (new Event(9, 2010, 'Commons Picture of the Year for 2009', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2009'))
            ->addRule(new DateRegisteredRule('<201001'), Workflow::ON_ANY_WIKI)// registered before 01 January 2010
            ->addRule(new EditCountRule(200, null, '<20100116'), Workflow::ON_ANY_WIKI);// 200 edits before 16 January 2010


        ##########
        ## 2010: steward elections (February)
        ##########
        // voters
        yield (new Event(8, 2010, '2010-02 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2010'))
            ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
            ->addRule(new EditCountRule(600, null, '<200911', EditCountRule::ACCUMULATE))// 600 edits before 01 November 2009
            ->addRule(new EditCountRule(50, '200908', '<201002', EditCountRule::ACCUMULATE))// 50 edits between 01 August 2009 and 31 January 2010
            ->withExtraRequirements(['Your account must not be primarily used for automated (bot) tasks.']);

        // candidates
        yield (new Event(7, 2010, '2010-02 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2010'))
            ->addRule(new DateRegisteredRule('<20091029'))// registered for three months
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
            ]);


        ##########
        ## 2010: create global sysops vote
        ##########
        yield (new Event(6, 2010, 'create global sysops vote', '//meta.wikimedia.org/wiki/Global_sysops/Vote'))
            ->addRule(new DateRegisteredRule('<200910'), Workflow::ON_ANY_WIKI)// registered for three months
            ->addRule(new EditCountRule(150, null, '<201001'), Workflow::ON_ANY_WIKI);// 150 edits before 01 January 2010


        ##########
        ## 2009: enwiki arbcom elections
        ##########
        yield (new Event(5, 2009, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2009'))
            ->addRule((new EditCountRule(150, null, '<20091102'))->inNamespace(0))// 150 main-namespace edits before 02 November 2009
            ->withonlyDB('enwiki_p');


        ##########
        ## 2009: Commons Picture of the Year for 2008
        ##########
        yield (new Event(4, 2009, 'Commons Picture of the Year for 2008', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2008'))
            ->addRule(new DateRegisteredRule('<200901'), Workflow::ON_ANY_WIKI)// registered before 01 January 2009
            ->addRule(new EditCountRule(200, null, '<20090212'), Workflow::ON_ANY_WIKI);// 200 edits before 12 February 2009


        ##########
        ## 2009: steward elections
        ##########
        // candidates
        yield (new Event(3, 2009, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2009'))
            ->addRule(new DateRegisteredRule('<200811'))// registered for three months before 01 November 2008
            ->withAction('<strong>be a candidate</strong>')
            ->withExtraRequirements([
                'You must be 18 years old, and at the age of majority in your country.',
                'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
                'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation.'
            ]);

        // voters
        yield (new Event(2, 2009, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2009'))
            ->addRule((new NotBlockedRule())->onWiki('metawiki'), Workflow::HARD_FAIL)
            ->addRule(new NotBotRule())
            ->addRule(new DateRegisteredRule('<200901'))// registered before 01 January 2009
            ->addRule(new EditCountRule(600, null, '<200811'))// 600 edits before 01 November 2008
            ->addRule(new EditCountRule(50, '200808', '<200902'))// 50 edits between 01 August 2008 and 31 January 2009
            ->withMinEditsForAutoselect(600);


        ##########
        ## 2008: enwiki arbcom elections
        ##########
        yield (new Event(1, 2008, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2008'))
            ->addRule((new EditCountRule(150, null, '<20081102'))->inNamespace(0))// 150 main-namespace before 02 November 2008
            ->withOnlyDB('enwiki_p');


        ##########
        ## 208: Wikimedia board elections
        ##########
        yield (new Event(0, 2008, 'Board elections', '//meta.wikimedia.org/wiki/Board elections/2008'))
            ->addRule(new NotBlockedRule())
            ->addRule(new NotBotRule())
            ->addRule(new EditCountRule(600, null, '<200803'))// 600 edits before 01 March 2008
            ->addRule(new EditCountRule(50, '200801', '<200806'))// 50 edits between 01 January and 29 May 2008
            ->withMinEditsForAutoselect(600);
    }
}
