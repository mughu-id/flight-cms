<?php

declare(strict_types=1);

/** @return array<string, string> slug => html */
function dummy_articles(): array
{
    $out = [];
    foreach (article_sources() as $slug => $sections) {
        $html = '';
        foreach ($sections as $heading => $paragraphs) {
            if (is_string($heading) && $heading !== 'lede') {
                $html .= '<h2>' . htmlspecialchars($heading) . '</h2>';
            }
            foreach ($paragraphs as $paragraph) {
                $html .= '<p>' . htmlspecialchars($paragraph) . '</p>';
            }
        }
        $out[$slug] = $html;
    }
    return $out;
}

/** @return array<string, array<string, list<string>>> */
function article_sources(): array
{
    return [
        'amazon-greenwood-factory' => robotics(),
        'white-house-openai-testers' => testers(),
        'openai-pauses-models' => leak(),
        'founders-seek-ipo-control' => ipo(),
        'meta-vr-glasses-price' => glasses(),
    ];
}

function robotics(): array
{
    return essay(
        'A robotics plant is not a ribbon and a press release. It is a floor, a power contract, a hiring calendar, and a road that already fails at shift change.',
        [
            'The filing' => [
                'Amazon’s Greenwood filing describes a plant that would assemble the low mobile robots used in warehouses, the machines that slide under a shelf and carry it to a person.',
                'The first production line is penciled for late 2028. That date is a target, not a promise, and the county has learned to read the difference.',
                'The site sits beside the old textile mill, which closed in a year most people here still use as a calendar mark.',
                'The floor has to hold test tracks. A robot that wanders in a lab is a demo. A robot that wanders on a customer floor is a recall.',
                'The application names a headcount in the high hundreds by the third year, with a smaller crew for construction and fit-out before that.',
                'Power is the quiet line item. The test hall wants a substation upgrade the utility has not scheduled.',
                'Truck traffic is the loud line item. The two-lane road already backs up behind the grain elevator on Friday afternoons.',
            ],
            'What the town is being asked to believe' => [
                'Greenwood has heard factory talk before. A battery plant was “in diligence” for fourteen months and then went to another state that offered a larger power hookup.',
                'This time the company bought an option on the land instead of leasing a conference room. Options expire. Purchases are harder to walk away from, and people noticed.',
                'The school district wants to know whether the jobs are for graduates of the existing vocational program or for technicians who will have to be recruited from two cities away.',
                'A wage sheet attached to the filing puts starting technician pay above the warehouse jobs at the edge of town and below the skilled-trades jobs at the river plant.',
                'That gap is the whole argument. If the work is closer to a trade, the town will train for it. If it is closer to a warehouse, the town already has that labor market.',
                'The mayor has been careful in public. She calls it “a serious filing” and will not call it a done deal until the power letter is signed.',
            ],
            'The machines themselves' => [
                'The robot in the drawings is not a humanoid. It is a rectangle on wheels, about the height of a suitcase, with a lifting plate and a camera that looks at the floor, not at people.',
                'Amazon has run versions of this machine in its own buildings for years. Building them for sale, or for a wider internal fleet, is a different factory problem.',
                'Final assembly is a sequence of torque checks, cable dress, and a calibration loop. The filing says the calibration loop is why the test hall is so long.',
                'A failed calibration is not scrap. The unit goes back two stations. The staffing plan assumes a rework rate the company will not publish.',
                'Suppliers named in an appendix are mostly domestic for frames and mostly imported for the motor controllers. That split will matter if tariffs move.',
                'Spare parts are supposed to ship from the same building for the first two years. After that, a separate service depot is “under study,” which means it is not funded.',
            ],
            'Labor' => [
                'The vocational school can add a robotics module in a year. It cannot add the instructors in a year unless someone pays for them.',
                'Amazon’s draft community agreement offers to fund two instructor salaries for three years. The district wants five years, because a program that dies when the grant dies is a press release.',
                'Night shift is where the numbers get thin. The filing assumes a full second shift by month eighteen. Greenwood’s current night-shift labor pool is the hospital and the grain co-op.',
                'Housing is not in the filing. A few hundred new households would not break the town, but they would fill the rental stock that teachers already struggle to enter.',
                'The union at the river plant has not been invited to anything. Organizers say they will leaflet the hiring office on the day it opens, whenever that is.',
                'Safety training in the draft is eight days. Veterans of the mill say eight days is enough to learn the stops and not enough to learn the exceptions.',
            ],
            'The road and the river' => [
                'County engineers have a one-page memo that the filing does not cite. It says the intersection at the grain elevator fails in the peak hour today.',
                'A turn lane would fix the peak hour and would not fix a shift change of several hundred cars. The memo says that part out loud.',
                'The company has offered a traffic study after groundbreaking. The county wants the study before the vote. This is the current standoff.',
                'Stormwater runs toward a ditch that already floods the lower fields in March. The plant’s roof is large. The ditch is not.',
                'A retention pond is in the site plan. The soil borings, which are public, show a clay layer that will make the pond slower to drain than the drawings assume.',
                'None of this is exotic. It is the ordinary work of putting a building on ground that already has a job.',
            ],
            'Money' => [
                'The incentive request is a property-tax abatement that steps down over ten years, plus a sales-tax exemption on the production equipment.',
                'The county’s own spreadsheet, released after a records request, shows the abatement is worth more in year three than the projected local wage tax in year three.',
                'That crossover is normal for these deals and is also the line critics will read aloud at the hearing.',
                'The state has a separate jobs credit that pays only if the headcount is still there in year five. The county abatement pays earlier. The timing is the risk.',
                'Amazon’s finance appendix uses a discount rate that makes the later jobs look smaller. Change the rate and the spreadsheet flips. Both sides know this.',
                'No one in the hearing will say “discount rate.” They will say “are the jobs real.” The rate is how that question gets a number.',
            ],
            'What late 2028 actually requires' => [
                'To hit a late-2028 line, concrete has to be poured in the dry season of 2027. That means permits this winter and a contractor under contract before spring.',
                'The utility’s substation queue is eighteen months in the last public report. Eighteen months from a signed letter is already tight against a 2028 start.',
                'If the letter slips one quarter, the line slips one year, because the dry season does not move.',
                'Hiring the first technicians a year before production is the plan. Hiring them into a building that is still a slab is how you lose them to the river plant.',
                'The company has done this sequence in other counties. Two of those projects opened on the year they promised. One opened two years late and smaller.',
                'Greenwood’s question is which of those it is signing up for, and the filing is not written to answer it.',
            ],
            'The hearing' => [
                'The planning commission meets on the first Thursday. The packet will be thick. Most of it will be drainage.',
                'Residents who live on the two-lane road have already asked for a night meeting. The commission usually refuses and then makes an exception when the room is full.',
                'The company will send a site lead and a lawyer. The site lead will talk about jobs. The lawyer will talk about the study.',
                'What will not be in the room is a finished robot. There is a photo in the packet. Photos do not make noise, and noise is what the road people came to ask about.',
                'A vote to continue is the likely outcome. A vote to deny would be unusual this early. A vote to approve would be reckless before the power letter.',
                'Between those, “continue” is how a county stays in a negotiation without pretending it is finished.',
            ],
        ]
    );
}

function essay(string $lede, array $sections): array
{
    $out = ['lede' => [$lede]];
    foreach ($sections as $heading => $paragraphs) {
        $out[$heading] = expand($paragraphs);
    }
    return $out;
}

/** @param list<string> $seeds */
function expand(array $seeds): array
{
    $out = [];
    foreach ($seeds as $seed) {
        $out[] = $seed . ' ' . follow($seed);
    }
    return $out;
}

function testers(): array
{
    return essay(
        'The White House did not ask the model to write a poem. It asked the model to read a 90-page briefing and say what was missing, and then it asked a person to say whether the model was right.',
        [
            'The room' => [
                'The session ran for three hours in a conference room with the windows covered. Phones stayed in a tray by the door. The agenda, released later with the names removed, had four items and no demonstration slot.',
                'OpenAI sent two researchers and one policy lead. The agencies sent analysts who already read these briefings for a living. That mix was the point. A model that impresses engineers and wastes an analyst is not a tool.',
                'The first briefing was unclassified and old, chosen so anyone could argue with it. The model summarized it in four minutes. The analysts spent forty minutes on what the summary dropped.',
                'It dropped a footnote that changed the cost estimate. It kept a number from the executive summary that the footnote had corrected. The analysts circled both.',
                'Nobody in the room called that a failure of intelligence. They called it the ordinary way a rushed reader behaves, which is not a compliment and not a reason to stop.',
                'The second document was longer and duller, a procurement history. The model found a repeated vendor name the analysts had also found. It missed that the vendor had changed ownership. The ownership was in an appendix.',
            ],
            'What they measured' => [
                'The score sheet was not a benchmark from a paper. It was three questions: did it save time, did it add an error, and did a person catch the error before it mattered.',
                'On the first briefing, it saved time and added an error, and a person caught it because the person already knew the footnote. That is the easy case.',
                'On the procurement history, it saved less time, because checking the appendix took as long as reading the appendix. The analysts wrote that down without decorating it.',
                'A third test asked the model to list questions a briefer should be ready for. The list was useful and slightly generic. Two of the ten questions were ones the chair had already planned to ask.',
                'The policy lead wanted to talk about future models. The chair kept the room on this model and this pile of paper. Future models were not in the room.',
                'No one was asked to approve a deployment. The session was a look, not a purchase. The distinction was repeated until it was boring, which was intentional.',
            ],
            'Errors and responsibility' => [
                'The argument that lasted was not about accuracy. It was about whose name goes on a sentence the model drafted and a person edited.',
                'Agency counsel said a draft is a draft, and the signer is the signer. The researchers agreed and then asked what the record should show about the draft.',
                'If the record shows nothing, a bad paragraph looks like it came from the analyst. If the record shows everything, the analyst spends the saved time on disclosure. Both costs are real.',
                'They did not settle it. They wrote “needs a rule” in the margin, which is what a morning like this is for.',
                'One analyst said she would use the tool to build a question list and would not use it to move a number from a table into a memo. The room treated that as a workable line.',
                'A number that moves without its footnote is how briefings go wrong even when every author is human. The model made the old mistake faster. Speed without the footnote is not a feature.',
            ],
            'What was not on the table' => [
                'There was no live connection to an internal system. The documents were brought in on paper and on a laptop that did not leave the building.',
                'Classified material was not used. A separate conversation about classified use is happening somewhere else, and this session was designed not to be that conversation.',
                'The researchers were not asked for customer stories. They were asked what the model does when the source is boring and long, which is most of the work.',
                'A demo of a fluent answer to an easy question would have filled the time and taught nothing. The chair had seen that demo. She had scheduled around it.',
                'Lunch was in the room. People kept working through it because the procurement appendix was still open and no one wanted to be the person who trusted the summary.',
                'At the end they collected the score sheets and the marked-up printouts. The printouts, not the chat log, are what the analysts trusted as the record.',
            ],
            'The memo afterward' => [
                'The memo that went upstairs is four pages. It says the tool can shorten a first read and cannot replace the second read. That sentence is the whole finding.',
                'It recommends a pilot on unclassified recurring briefs, with a human check on every number, and no pilot on anything that leaves the building the same day.',
                'It asks for a disclosure line when a draft was machine-assisted. It does not specify the words. Counsel will fight over the words later, which is their job.',
                'It notes that the model was confident about the uncorrected number. Confidence is not a field these analysts respect. They respect the footnote.',
                'OpenAI’s policy lead asked to see the memo. She was told she would see the unclassified version, which is most of it, because the session was built that way.',
                'Whether a second session happens depends on whether the pilot is funded. Funding is not a research question. It is a budget line in a different meeting.',
            ],
            'Why the dull test matters' => [
                'Public arguments about these systems are usually about spectacular errors or spectacular demos. Analysts live in the middle, where the error is a stale number in a long PDF.',
                'A stale number that sounds official will travel farther than a spectacular error, because nobody forwards a spectacular error into a decision memo.',
                'The room’s refusal to be impressed is the part worth keeping. They graded a clerical skill: did you carry the correction forward.',
                'Carrying the correction forward is what junior staff are taught in their first month. A tool that fails that lesson is not ready to sit in the first month’s chair.',
                'None of this says the tool is useless. The question list was good. The vendor catch was good. The footnote miss was the price of using it carelessly.',
                'Careful use is a workflow, not a warning label. The memo’s pilot is an attempt to write that workflow down before anyone pretends it is obvious.',
            ],
        ]
    );
}


function follow(string $seed): string
{
    $tails = [
        'People who have sat through the last two recruitment cycles say the constraint is not interest. It is whether a new hire can see a second year. A posting that reads like a pilot will draw applicants who are already planning their exit, and a plant cannot calibrate robots with a workforce that is half packed.',
        'The public record is thinner than the rumor. What is on paper is a date, a headcount range, and a list of studies still to be commissioned. What is not on paper is the penalty if the date moves, and in this county a date without a penalty is a wish.',
        'Neighbors have started keeping their own notes. They count trucks at the elevator, they photograph the ditch after rain, and they will bring both to the hearing. A company that has not walked the road at 4 p.m. on a Friday will be told what 4 p.m. on a Friday looks like.',
        'Engineers who reviewed the packet for the district said the drawings are competent and incomplete. Competent means the building can stand. Incomplete means the building’s effect on the road, the ditch, and the night shift is still a paragraph instead of a design.',
        'The comparison everyone reaches for is the mill. The mill paid well, trained on the job, and then ended. Any new employer is being measured against both halves of that memory, the wages and the ending, and a filing that speaks only to the wages is only half an answer.',
        'There is also the smaller question of who maintains the place after the ribbon. Construction jobs leave. Maintenance jobs stay. The staffing chart puts maintenance in year two, which is late if the machines are as fussy as the calibration loop implies.',
        'A teacher at the vocational school put it more plainly. She can add a course. She cannot add a course, an instructor, a lab, and a promise to families in the same budget year unless the money is in an agreement and not in a speech.',
        'None of the skeptics are asking the company to cancel. They are asking it to sign the unglamorous documents first: the power letter, the traffic study, the instructor contract, the pond that actually drains. The announcement can follow the documents. It usually happens the other way around.',
    ];
    $n = abs(crc32($seed)) % count($tails);
    $extra = [
        'Local contractors expect the civil work to be bid in packages, not as one job, because no single firm in the county has the bonding for the whole pad.',
        'The state’s workforce office will count a job only if it lasts ninety days, which is why the year-five credit and the local pride in “jobs” are not the same statistic.',
        'Insurance on a test hall full of moving machines is a line the filing leaves as an allowance. Allowances are where projects learn they were optimistic.',
        'If the second shift never fills, the building still exists. An underused plant is a tax abatement attached to a quiet parking lot, and the county has one of those already.',
    ];
    $m = abs(crc32(strrev($seed))) % count($extra);
    return $tails[$n] . ' ' . $extra[$m];
}

function leak(): array
{
    return essay(
        'The model was not supposed to have that folder. The agent had it anyway, and for eleven hours the folder was one careless prompt away from a chat window.',
        [
            'The pause' => [
                'OpenAI stopped a test build on a Thursday night and said so on Friday morning in a short note that used the word paused and not the word breached.',
                'The note said no customer data had been confirmed public. Confirmed is a narrow word. The engineers spent the night trying to make it true.',
                'The agent was part of a tool that reads a workspace and answers questions about it. A permission list was supposed to keep it inside one project. The list was wrong.',
                'Wrong meant an internal notes directory was mounted where a sample dataset should have been. The notes were not secrets in the cinematic sense. They were the ordinary private record of how a team argues.',
                'An engineer noticed because the agent quoted a sentence she had written in a doc that the test project should not have seen. She recognized her own aside.',
                'She filed the ticket at 6:40 p.m. The build was stopped at 9:10 p.m. The gap is what the security review is now about.',
            ],
            'What an agent permission is' => [
                'A chatbot answers from a prompt. An agent also reads, and sometimes writes, and the difference is the whole risk.',
                'The permission model was a list of paths. Lists of paths rot. A rename, a symlink, a copied folder from a demo, and the list describes a place that no longer matches the disk.',
                'The demo folder had been copied by a contractor who needed realistic documents and was told not to use production. He used a snapshot that was realistic because it was real.',
                'Nobody’s checklist said to diff the snapshot against the permission list. The checklist said the snapshot was approved. Approved, here, meant someone had glanced at the filenames.',
                'Filenames do not show that a directory of meeting notes was one level down. The agent could walk down. Walking down was the feature.',
                'After the pause, the feature is still the feature. The question is whether walking down requires a second allow, written by a person, every time the tree changes.',
            ],
            'The eleven hours' => [
                'Logs show the agent answered internal questions for testers during those hours. The answers that quoted the notes were few. Few is not none.',
                'The testers were employees and two partner researchers under an evaluation agreement. The agreement allows them to see evaluation data. It does not allow them to see another team’s meeting notes.',
                'Whether any tester saved an answer is the part that took all night. Browser history is not a forensic record. People paste into other tools.',
                'By morning the company could say it had found no evidence of a save outside the evaluation environment. It could not say a paste was impossible. The note to staff used the first sentence.',
                'The two partner researchers were called. Both said they had not copied anything. One had a screenshot of a different answer, which did not include the notes, and he sent it without being asked.',
                'That cooperation is why the note could be short. A researcher who had gone quiet would have made Friday a different kind of day.',
            ],
            'Training and the kill switch' => [
                'The pause also stopped a training run that depended on the same build. Stopping a run is expensive and visible, which is why it is the right signal and also why people hesitate.',
                'The hesitation is measurable. The ticket sat for two and a half hours before the on-call lead with the authority to kill the run was reached. He was at dinner. The backup on-call did not believe he had the authority.',
                'A kill switch that requires a belief about authority is not a kill switch. It is a meeting. The review will say this more politely.',
                'The training run was not learning from the notes. It was simply using the same code. Stopping it was about the code, not about the data in the run. That distinction was lost in the first internal retelling and had to be put back.',
                'Restarting the run later is a scheduling problem. The cluster was given to another job overnight. Getting it back will take days, not because of the incident, but because clusters are always promised twice.',
                'Customers of the public product were unaffected. Saying so early was correct and also insufficient, because the fear was never really about the public product. It was about agents.',
            ],
            'What changes on Monday' => [
                'The evaluation environment now mounts a fresh, empty tree unless a named owner attaches a dataset. The owner’s name is in the job, not in a wiki.',
                'Snapshots require a manifest that lists every directory, generated by a script, not by a person recalling what they copied.',
                'The backup on-call can kill a run. The page that says so was updated before Friday ended, which is the one change that did not wait for the review.',
                'A red-team prompt that asks “what documents can you see that you should not” is now in the daily suite. It would have caught this. It was on a quarterly suite. Quarterly is how eleven hours happen.',
                'Partner researchers will get a one-page description of what their environment is allowed to contain. The old agreement assumed they would not look around. Agents look around. That is what they are for.',
                'None of these changes make agents safe. They make this failure harder to repeat by accident. Deliberate misuse is a different document.',
            ],
            'The sentence that matters' => [
                'The engineer recognized her own aside. That is an accident of authorship, not a control. The next leak will not be written in a voice anyone on duty happens to remember.',
                'A control has to notice a path that is not on the list, even when the text looks boring. Boring text is what internal notes are.',
                'The company’s note avoided the word breach because a breach, in their glossary, means customer data confirmed outside the boundary. The glossary is not what employees heard.',
                'Employees heard that an agent read a folder it should not have read, and that the folder stayed readable for a shift. That hearing is accurate.',
                'Accurate and unclassified is a good place to start a review. The review will be longer than the note. It should be. Eleven hours is enough time to deserve more than a paragraph.',
                'What the public is owed is smaller than what the staff is owed, and the staff is owed the kill-switch page that was fixed on Friday and the manifest that was not yet built.',
            ],
        ]
    );
}

function ipo(): array
{
    return essay(
        'Seven people who joined before the company had a real office want more than half the votes when it lists. The cap table was not built for that sentence.',
        [
            'The ask' => [
                'The proposal is a dual-class structure that would leave the founding group with about 51 percent of the voting power and a much smaller share of the economics.',
                'It arrived as a memo to the board, not as a leak. The memo is six pages and it is already in the hands of two investors who were not supposed to have it yet.',
                'The seven are not all founders in the press-release sense. Two are. Three were the first engineers. Two ran the early sales motion and have been called founders in interviews because it was easier.',
                'Their combined common stock, today, is nowhere near a majority of anything. The 51 percent would be created by a new class of shares with ten votes each, issued to them at the listing.',
                'That is a standard trick and it is also a request for everyone else to accept less say on the day the company asks the public for money.',
                'The memo argues the public is buying a plan that only this group will stick to. Investors who have heard that sentence before answered with a question: stick to it for how long.',
            ],
            'Sunset' => [
                'The draft has no sunset. The extra votes last as long as the holders keep a minimum number of shares, and the minimum is low.',
                'A sunset of seven years is what one large investor has already floated, informally, through a lawyer who was careful not to call it a counteroffer.',
                'Seven years is the length of a typical fund’s patience and not the length of a product cycle. The founders say a product cycle is the wrong clock.',
                'They want the clock tied to a share-price trigger or to a handover, not to a date. Dates, they argue, force a sale of the company to the calendar.',
                'Investors hear a clock with no date as a clock that does not exist. This is the actual negotiation, underneath the language about vision.',
                'A compromise that is common elsewhere is a sunset that extends if the holders still own a real stake and the board, including independents, votes to extend it. Nobody in this memo has offered that.',
            ],
            'Who loses a vote' => [
                'Employees hired after the early years hold options on the low-vote stock. A ten-vote class for seven people does not take their shares. It takes the weight of their shares.',
                'The employee representative on the board asked for a modeled example. In the model, a union of every employee outside the seven cannot outvote the seven on a contested director.',
                'That is the point of the structure and it is also the sentence that will be hard to say at an all-hands.',
                'Early investors who are selling in the listing do not care about votes they are about to give up. Investors who are staying care a great deal.',
                'One fund that led the last round has a protective provision that already lets it block a new class of stock. The memo cannot pass without that fund, and the fund has not nodded.',
                'The protective provision is why this is a negotiation and not an announcement. Announcements come after the provision is waived. It has not been waived.',
            ],
            'The public market' => [
                'Index funds will buy the low-vote shares if the company is large enough, because that is what the mandate says. They will also vote no on the structure when they are asked, and then buy anyway.',
                'That pattern is well known and it is a weak defense. “They always buy” is not a reason. It is a description of a buyer who is not choosing.',
                'Active managers have been louder lately about dual class, especially when there is no sunset. Two of them own pieces of the last round through side vehicles.',
                'Bankers on the draft cover say the discount for a no-sunset dual class is real and smaller than founders fear. Bankers are not the ones who live with the discount.',
                'The stronger market objection is not price. It is the next vote: a sale, a large acquisition, a related-party deal. Super-voting stock matters most on the day the interests diverge.',
                'The memo says the interests will not diverge because the seven are the culture. Cultures are not a voting agreement. A voting agreement is a voting agreement.',
            ],
            'What the board can do' => [
                'The board can reject the memo, accept it, or send it back with a sunset and a higher minimum holding. Sending it back is the likely middle.',
                'Rejection has a cost. Two of the seven are still the only people who can ship the core product without a long rewrite. A sulking founder is not a governance theory. It is an operational fact.',
                'Acceptance has a cost that shows up later, in a shareholder suit or in a buyer who pays less because control does not come with the shares.',
                'The independent directors have their own counsel. That counsel has already asked whether the ten-vote share is a compensation decision in disguise, which would change the process.',
                'If it is compensation, it wants a compensation-committee record, a peer set, and a number. The memo has a narrative and not a peer set.',
                'Process will not settle the substance. It will decide whether the substance can be voted in a form a later judge can read.',
            ],
            'A majority of what' => [
                'Fifty-one percent of votes is not fifty-one percent of the company. The memo is careful about this. Readers in a hurry will not be.',
                'The hurry is the risk. A headline that says founders will own the company is false and will be repeated. The correction is a subordinate clause.',
                'What they would own is the outcome of contested votes, for as long as the class exists, subject to whatever sunset survives the next draft.',
                'Employees who are deciding whether to stay through the listing will do that math in terms of their own options, not in terms of the narrative.',
                'If the options are on a class that cannot win a vote, the options are a bet on price alone. Some people are fine with that. The ones who wanted a say will notice.',
                'The next draft will be shorter or it will fail. Six pages was enough to start the argument. The argument now fits in the sunset clause and the minimum holding, and those two lines are where it will be won or given up.',
            ],
        ]
    );
}

function glasses(): array
{
    return essay(
        'The new glasses are cheaper because they do less, and the bet is that less is what people actually wear.',
        [
            'The price' => [
                'Meta’s spring glasses come in under the last model by a margin large enough to matter at a checkout and small enough to protect the older stock.',
                'The cut is not a sale. It is a different bill of materials: a simpler display, one fewer camera, and a frame that uses a hinge the supplier already makes for ordinary sunglasses.',
                'Retail partners were told the cheaper version arrives next quarter. They were also told not to dump the current version, which is why the price gap is a step and not a cliff.',
                'A cliff would make everyone holding inventory angry. A step lets the old model sit as the “pro” without a new feature to justify the word.',
                'The company’s own stores will push the cheaper pair at the front. The older pair moves to a shelf that requires a question. Questions reduce sales. That is the point.',
                'Analysts who wanted a subsidy, a pair sold below cost to buy an audience, did not get one. The gross margin in the briefing is lower than phones and higher than a giveaway.',
            ],
            'Weight' => [
                'The complaint that killed daily use of the last pair was weight on the nose after an hour. The new pair claims a cut measured in grams that the briefing treats as the whole product.',
                'Grams are real if they are in the front of the frame. Grams saved in the temple do less for the bruise on the nose. The briefing is specific about where the weight left.',
                'A reviewer who wore the last pair to dinner said she took them off before the bill. The new target is still being worn when the bill arrives. That is the test the team repeats.',
                'Battery life did not improve. It moved. A smaller cell lives in a case, and the glasses sip from it. The case is another object to lose, which the team knows and is betting against.',
                'People already lose earbuds. A case is not a new behavior. It is the earbud behavior moved to the face, and the face is less forgiving of a dead device at dinner.',
                'The industrial designers won an argument against a thicker rim that would have held the old battery. The argument is visible. The rim is the product’s apology for last year.',
            ],
            'What it will not do' => [
                'There is no full virtual screen. The display is a small region, enough for a caption, a message, a translation line. It is not a monitor.',
                'People who wanted a monitor will be disappointed and were not the buyer in the new plan. The buyer in the new plan is someone who would wear sunglasses anyway.',
                'The camera that was removed is the one that did depth. Photos remain. The spatial tricks that needed depth are gone, and the software that depended on them is hidden.',
                'Hiding a feature is how you ship a cheaper object without a support nightmare. The app simply does not offer the mode. There is no error. There is an absence.',
                'Audio stays, because audio is the feature people used. The briefing’s usage chart shows captions and music far ahead of anything spatial. The chart is why the camera lost.',
                'A product that keeps the used features and drops the photographed features is a grown-up cut. It will look worse in a keynote and better in a return rate.',
            ],
            'Stores' => [
                'Training for floor staff is two hours, down from a half day, because there is less to demonstrate. A demonstration that needs a half day is a demonstration that does not happen at noon.',
                'The script tells staff to put the glasses on the customer before talking about the chip. Last year’s script started with the chip. The chip did not sell the hour of comfort.',
                'Returns on the last model clustered in the first ten days and cited comfort. The new return policy is the same length. The bet is that the cluster moves, not that the policy needs to.',
                'Prescription inserts are the unglamorous partnership. Without them the buyer is anyone who does not need glasses, which is a smaller market than the keynote implies.',
                'The insert vendor can deliver in four days in two cities and in two weeks elsewhere. Two weeks is long enough to lose the impulse that the lower price is supposed to create.',
                'So the cheaper glasses will sell fastest where the insert is fast, which is a logistics fact and not a demand fact. Demand stories that ignore the insert are stories about a different product.',
            ],
            'Privacy, again' => [
                'A camera on a face is the objection that does not care about the price. The recording light is still there. It is slightly larger, because the last one was criticized as coy.',
                'Larger is not the same as trusted. A light can be covered. The briefing does not claim otherwise. It claims the default capture is shorter and more obvious.',
                'Shops and venues that banned the last pair will not unban this one because it costs less. A ban is about the lens, not the receipt.',
                'The company’s answer is a clearer indicator and a shutter sound that cannot be turned off in some regions. Regions where it can be turned off will be the ones in the complaint videos.',
                'None of this is new. What is new is shipping it on the cheaper object, the one more people might actually wear. A rare device is a curiosity. A common device is a rule.',
                'If the return rate falls and the wear time rises, the privacy argument gets louder, not quieter, because there are more faces in more rooms. The team has written that tradeoff down. They have not solved it.',
            ],
            'A pair you forget' => [
                'The internal phrase for success is boring. Boring means the owner stops narrating the glasses and starts narrating the dinner.',
                'Last year’s owners narrated the glasses. That was good for a month of videos and bad for a year of use. The usage curve fell off in week three. Week three is the chart on the wall.',
                'The cheaper pair is aimed at week four. Everything cut was something that made a good video and a bad Wednesday.',
                'A good Wednesday is captions you can read, music that does not hurt, and a frame you forget until someone asks. The someone asking is the remaining problem.',
                'Retail will know by the first return cycle, which is faster and more honest than a keynote. Keynotes do not have noses.',
                'If the cycle looks like last year, the step down in price will not be enough, and the next argument will be about the nose again, not about the chip.',
            ],
        ]
    );
}


