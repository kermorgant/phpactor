<?php

namespace Phpactor\Extension\CodeTransform\Rpc;

use Phpactor\Application\ClassGenerator;
use Phpactor\Extension\Rpc\Response\Input\TextInput;
use Phpactor\Extension\Rpc\Response\Input\ChoiceInput;
use Phpactor\Extension\Rpc\Response\InputCallbackResponse;
use Phpactor\Extension\Rpc\Request;
use Phpactor\Extension\CodeTransform\Application\Exception\FileAlreadyExists;
use Phpactor\Extension\Rpc\Response\OpenFileResponse;
use Phpactor\Extension\Rpc\Response\EchoResponse;
use Phpactor\Extension\Rpc\Response\Input\ConfirmInput;
use Phpactor\Extension\CodeTransform\Application\AbstractClassGenerator;
use Phpactor\Extension\Rpc\Handler\AbstractHandler;

abstract class AbstractClassGenerateHandler extends AbstractHandler
{
    const PARAM_CURRENT_PATH = 'current_path';
    const PARAM_NEW_PATH = 'new_path';
    const PARAM_VARIANT = 'variant';
    const PARAM_OVERWRITE = 'overwrite';

    /**
     * @var ClassGenerator
     */
    protected $classGenerator;

    public function __construct(AbstractClassGenerator $classGenerator)
    {
        $this->classGenerator = $classGenerator;
    }

    public function defaultParameters(): array
    {
        return [
            self::PARAM_CURRENT_PATH => null,
            self::PARAM_NEW_PATH => null,
            self::PARAM_VARIANT => null,
            self::PARAM_OVERWRITE => null,
        ];
    }

    abstract protected function generate(array $arguments);

    abstract protected function newMessage(): string;

    public function handle(array $arguments)
    {
        if (false === $arguments[self::PARAM_OVERWRITE]) {
            return EchoResponse::fromMessage('Cancelled');
        }

        $missingInputs = [];

        if (null === $arguments[self::PARAM_VARIANT]) {
            $this->requireInput(ChoiceInput::fromNameLabelChoicesAndDefault(
                self::PARAM_VARIANT,
                'Variant: ',
                array_combine(
                    $this->classGenerator->availableGenerators(),
                    $this->classGenerator->availableGenerators()
                )
            ));
        }

        $this->requireInput(TextInput::fromNameLabelAndDefault(
            self::PARAM_NEW_PATH,
            $this->newMessage(),
            $arguments[self::PARAM_CURRENT_PATH],
            'file'
        ));

        if ($this->hasMissingArguments($arguments)) {
            return $this->createInputCallback($arguments);
        }

        try {
            $newPath = $this->generate($arguments);
        } catch (FileAlreadyExists $e) {
            return InputCallbackResponse::fromCallbackAndInputs(
                Request::fromNameAndParameters(
                    $this->name(),
                    [
                        self::PARAM_CURRENT_PATH => $arguments[self::PARAM_CURRENT_PATH],
                        self::PARAM_NEW_PATH => $arguments[self::PARAM_NEW_PATH],
                        self::PARAM_VARIANT => $arguments[self::PARAM_VARIANT],
                        self::PARAM_OVERWRITE => null,
                    ]
                ),
                [
                    ConfirmInput::fromNameAndLabel(
                        self::PARAM_OVERWRITE,
                        'File already exists, overwrite? :'
                    )
                ]
            );
        }

        return OpenFileResponse::fromPath($newPath);
    }
}
