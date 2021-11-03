<?php

namespace ChromeDevtoolsProtocol;

use ChromeDevtoolsProtocol\Exception\ErrorException;
use ChromeDevtoolsProtocol\Instance\Launcher;
use ChromeDevtoolsProtocol\Model\Network\EnableRequest;
use ChromeDevtoolsProtocol\Model\Page\NavigateRequest;
use ChromeDevtoolsProtocol\Model\Security\CertificateErrorActionEnum;
use ChromeDevtoolsProtocol\Model\Security\CertificateErrorEvent;
use ChromeDevtoolsProtocol\Model\Security\HandleCertificateErrorRequest;
use ChromeDevtoolsProtocol\Model\Security\SetOverrideCertificateErrorsRequest;
use PHPUnit\Framework\TestCase;

class DevtoolsClientTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testHandleCertificateError()
    {
        $ctx      = Context::withTimeout(Context::background(), 10);
        $launcher = new Launcher();
        $instance = $launcher->launch($ctx);
        try {
            $tabs = $instance->tabs($ctx);
            $this->assertEquals(1, sizeof($tabs));
            if (sizeof($tabs) != 1) {
                throw new \Exception('Failed to retrieve tab!');
            }
            $tab = $tabs[0];

            $client = $tab->devtools();
            try {
                $client->page()->enable($ctx);
                $client->network()->enable($ctx, EnableRequest::make());
                $client->security()->enable($ctx);
                $client->security()->setOverrideCertificateErrors(
                    $ctx,
                    SetOverrideCertificateErrorsRequest::builder()
                        ->setOverride(true)
                        ->build()
                );
                $client->security()->addCertificateErrorListener(function (CertificateErrorEvent $ev) use (
                    $ctx,
                    $client
                ) {
                    $client->security()->handleCertificateError(
                        $ctx,
                        HandleCertificateErrorRequest::builder()
                            ->setEventId($ev->eventId)
                            ->setAction(CertificateErrorActionEnum::CONTINUE)
                            ->build()
                    );
                });
                $client->page()->navigate($ctx,
                    NavigateRequest::builder()->setUrl("https://untrusted-root.badssl.com/")->build());
                $client->page()->awaitLoadEventFired($ctx);

                $this->assertTrue(true, "Ok, handling certificate errors works");
            } finally {
                $client->close();
            }
        } finally {
            $instance->close();
        }
    }

    public function testErrorHandling()
    {
        $this->expectException(ErrorException::class);

        $ctx      = Context::withTimeout(Context::background(), 10);
        $launcher = new Launcher();
        $instance = $launcher->launch($ctx);
        try {
            $tabs = $instance->tabs($ctx);
            foreach ($tabs as $tab) {
                $tab->close($ctx);
            }

            $tab = $instance->open($ctx);
            $this->assertEquals("about:blank", $tab->url);

            /** @var DevtoolsClientInterface & InternalClientInterface $client */
            $client = $tab->devtools();
            try {

                $client->executeCommand($ctx, "SomeCommand.thatDoesNotExist", new \stdClass());
            } finally {
                $client->close();
            }
        } finally {
            $instance->close();
        }
    }
}
