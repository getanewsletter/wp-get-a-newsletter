import { registerBlockType } from '@wordpress/blocks';
import { useEffect, useState } from '@wordpress/element';
import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { SelectControl, Spinner, TextControl, PanelBody, CheckboxControl, RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './style.css';

registerBlockType('gan/newsletter-form', {
    title: __('Get a Newsletter', 'getanewsletter'),
    icon: 'email',
    category: 'widgets',
    attributes: {
        formId: {
            type: 'string',
            default: '',
        },
        isTitleEnabled: {
            type: 'boolean',
            default: false,
        },
        formTitle: {
            type: 'string',
            default: __('Join our newsletter', 'getanewsletter'),
        },
        isDescriptionEnabled: {
            type: 'boolean',
            default: false,
        },
        formDescription: {
            type: 'string',
            default: __('Enter your details below to sign up for our newsletter', 'getanewsletter'),
        },
        appearance: {
            type: 'string',
            default: 'square',
        },
        fieldBackground: {
            type: 'string',
            default: '#ffffff',
        },
        fieldBorder: {
            type: 'string',
            default: '#000000',
        },
        labelColor: {
            type: 'string',
            default: '#000000',
        },
        buttonBackground: {
            type: 'string',
            default: '#0280FF',
        },
        buttonTextColor: {
            type: 'string',
            default: '#ffffff',
        },
        errorMessage: {
            'type': 'string',
            'default': ''
        },
        uniqueId: {
            type: 'string',
            default: ''
        }
    },
    edit: ({ attributes, setAttributes, clientId }) => {
        const [forms, setForms] = useState([]);
        const [formData, setFormData] = useState(null);
        const [isLoadingForms, setIsLoadingForms] = useState(true);
        const [isLoadingFormData, setIsLoadingFormData] = useState(false);

        useEffect(() => {
            setIsLoadingForms(true);
            fetch(`${ganAjax.ajaxurl}?action=gan_get_subscription_forms_list`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setForms(data.data);
                    } else {
                        setAttributes({ errorMessage: data.data });
                    }
                    setIsLoadingForms(false);
                });
        }, []);

        useEffect(() => {
            if (attributes.formId) {
                setIsLoadingFormData(true);
                fetch(`${ganAjax.ajaxurl}?action=gan_get_subscription_form`, {
                    method: 'POST',
                    body: new URLSearchParams({ form_id: attributes.formId }),
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            setFormData(data.data);
                        } else {
                            setAttributes({ errorMessage: data.data });
                        }
                        setIsLoadingFormData(false);
                    });
            }
        }, [attributes.formId]);

        useEffect(() => {
            const blockElement = document.querySelector(`.gan-block-form-${attributes.uniqueId}`);
            if (blockElement) {
                blockElement.style.setProperty('--gan-border-radius', attributes.appearance === 'rounded' ? '8px' : '0');
                blockElement.style.setProperty('--gan-field-background', attributes.fieldBackground);
                blockElement.style.setProperty('--gan-field-border', attributes.fieldBorder);
                blockElement.style.setProperty('--gan-label-color', attributes.labelColor);
                blockElement.style.setProperty('--gan-button-background', attributes.buttonBackground);
                blockElement.style.setProperty('--gan-button-text-color', attributes.buttonTextColor);
            }
        }, [attributes.appearance, attributes.fieldBackground, attributes.fieldBorder, attributes.labelColor, attributes.buttonBackground, attributes.buttonTextColor, attributes.uniqueId]);

        useEffect(() => {
            setAttributes({ uniqueId: clientId });
        }, [clientId]);

        const handleFormChange = (formId) => {
            setAttributes({ formId });
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Form Settings', 'getanewsletter')}>
                        {isLoadingForms ? (
                            <Spinner />
                        ) : (
                            <SelectControl
                                label={__('Select form', 'getanewsletter')}
                                value={attributes.formId}
                                options={[
                                    { label: __('Choose a form', 'getanewsletter'), value: '', disabled: true },
                                    ...forms.map(form => ({
                                        label: form.name,
                                        value: form.key,
                                    }))
                                ]}
                                onChange={handleFormChange}
                            />
                        )}
                        <CheckboxControl
                            label={__('Title', 'getanewsletter')}
                            checked={attributes.isTitleEnabled}
                            onChange={(isTitleEnabled) => setAttributes({ isTitleEnabled })}
                        />
                        {attributes.isTitleEnabled && (
                            <TextControl
                                label={__('Title', 'getanewsletter')}
                                value={attributes.formTitle}
                                onChange={(formTitle) => setAttributes({ formTitle })}
                                placeholder="Join our newsletter"
                            />
                        )}
                        <CheckboxControl
                            label={__('Description', 'getanewsletter')}
                            checked={attributes.isDescriptionEnabled}
                            onChange={(isDescriptionEnabled) => setAttributes({ isDescriptionEnabled })}
                        />
                        {attributes.isDescriptionEnabled && (
                            <TextControl
                                label={__('Description', 'getanewsletter')}
                                value={attributes.formDescription}
                                onChange={(formDescription) => setAttributes({ formDescription })}
                                placeholder="Enter your details below to sign up for our newsletter"
                            />
                        )}
                        <RadioControl
                            label={__('Appearance', 'getanewsletter')}
                            selected={attributes.appearance}
                            options={[
                                { label: __('Square corners', 'getanewsletter'), value: 'square' },
                                { label: __('Rounded corners', 'getanewsletter'), value: 'rounded' },
                            ]}
                            onChange={(appearance) => setAttributes({ appearance })}
                        />
                    </PanelBody>
                    <PanelColorSettings
                        title={__('Color settings', 'getanewsletter')}
                        colorSettings={[
                            {
                                value: attributes.fieldBackground,
                                onChange: (fieldBackground) => setAttributes({ fieldBackground }),
                                label: __('Input field – Background', 'getanewsletter'),
                            },
                            {
                                value: attributes.fieldBorder,
                                onChange: (fieldBorder) => setAttributes({ fieldBorder }),
                                label: __('Input field – Border', 'getanewsletter'),
                            },
                            {
                                value: attributes.labelColor,
                                onChange: (labelColor) => setAttributes({ labelColor }),
                                label: __('Label', 'getanewsletter'),
                            },
                            {
                                value: attributes.buttonBackground,
                                onChange: (buttonBackground) => setAttributes({ buttonBackground }),
                                label: __('Button – Background', 'getanewsletter'),
                            },
                            {
                                value: attributes.buttonTextColor,
                                onChange: (buttonTextColor) => setAttributes({ buttonTextColor }),
                                label: __('Button – Text', 'getanewsletter'),
                            },
                        ]}
                    />
                </InspectorControls>
                <div className={`gan-block-form gan-block-form-${attributes.uniqueId}`}>
                    {attributes.errorMessage && (
                        <div className="error-message">
                            {attributes.errorMessage}
                        </div>
                    )}
                    {!attributes.formId && (
                        <>
                            {isLoadingForms ? (
                                <Spinner />
                            ) : (
                                <SelectControl
                                    label={__('Select form', 'getanewsletter')}
                                    value={attributes.formId}
                                    options={[
                                        { label: __('Choose a form', 'getanewsletter'), value: '', disabled: true },
                                        ...forms.map(form => ({
                                            label: form.name,
                                            value: form.key,
                                        }))
                                    ]}
                                    onChange={handleFormChange}
                                />
                            )}
                        </>
                    )}
                    {attributes.isTitleEnabled && (
                        <h2 class="gan-block-form--title">{attributes.formTitle}</h2>
                    )}
                    {attributes.isDescriptionEnabled && (
                        <p class="gan-block-form--description">{attributes.formDescription}</p>
                    )}
                    {isLoadingFormData ? (
                        <Spinner />
                    ) : (
                        formData && (
                        <form method="post" className="newsletter-signup" action="javascript:alert('success!');">
                            {formData.form.first_name && (
                                <div className='gan-block-form--input-field'>
                                    <label htmlFor={`id_first_name_${clientId}`}>{formData.form.first_name_label || __('First name', 'getanewsletter')}</label>
                                    <input id={`id_first_name_${clientId}`} type="text" className="text" name="id_first_name" />
                                </div>
                            )}
                            {formData.form.last_name && (
                                <div className='gan-block-form--input-field'>
                                    <label htmlFor={`id_last_name_${clientId}`}>{formData.form.last_name_label || __('Last name', 'getanewsletter')}</label>
                                    <input id={`id_last_name_${clientId}`} type="text" className="text" name="id_last_name" />
                                </div>
                            )}
                            <div className='gan-block-form--input-field'>
                                <label htmlFor={`id_email_${clientId}`}>{__('Email address', 'getanewsletter')}</label>
                                <input id={`id_email_${clientId}`} type="text" className="email" name="id_email" />
                            </div>
                            {formData.customAttributes.map(attribute => (
                                formData.form.attributes.includes(attribute.code) && (
                                    <div className='gan-block-form--input-field' key={attribute.code}>
                                        <label htmlFor={`attr_${attribute.code}_${clientId}`}>{attribute.name}</label>
                                        <input id={`attr_${attribute.code}_${clientId}`} type="text" className="text" name={`attributes[${attribute.code}]`} />
                                    </div>
                                )
                            ))}
                            <div className="gan-button-container">
                                <button className="gan-button-container--button" type="submit">{formData.form.button_text || __('Subscribe', 'getanewsletter')}</button>
                            </div>
                        </form>
                        )
                    )}
                    <div className="news-note"></div>
                </div>
            </>
        );
    },
    save: () => null,
});
